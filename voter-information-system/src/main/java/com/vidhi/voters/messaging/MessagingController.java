package com.vidhi.voters.messaging;

import com.vidhi.voters.admin.AuditService;
import com.vidhi.voters.config.Languages;
import com.vidhi.voters.config.WorkContext;
import com.vidhi.voters.messaging.provider.SendResult;
import com.vidhi.voters.service.VoterFilter;
import com.vidhi.voters.web.Messages;
import jakarta.validation.Valid;
import org.springframework.data.domain.PageRequest;
import org.springframework.http.HttpStatus;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.validation.BindingResult;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.server.ResponseStatusException;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;

import java.time.LocalDateTime;
import java.util.List;

/**
 * Messages menu. MESSAGE_SEND: compose, send and follow campaigns. MESSAGE_ADMIN: templates, opt-outs, provider settings.
 */
@Controller
@RequestMapping("/messages")
@PreAuthorize("hasAuthority('MESSAGE_SEND')")
public class MessagingController {

    private final MessagingService service;
    private final MessageGateway gateway;
    private final MessageTemplateRepository templates;
    private final CampaignRepository campaigns;
    private final OutboxRepository outbox;
    private final OptOutRepository optOuts;
    private final OutboxWorker worker;
    private final MessagingProperties props;
    private final WorkContext work;
    private final Messages msg;
    private final AuditService audit;

    public MessagingController(MessagingService service, MessageGateway gateway, MessageTemplateRepository templates,
                               CampaignRepository campaigns, OutboxRepository outbox, OptOutRepository optOuts, OutboxWorker worker,
                               MessagingProperties props, WorkContext work, Messages msg, AuditService audit) {
        this.service = service;
        this.gateway = gateway;
        this.templates = templates;
        this.campaigns = campaigns;
        this.outbox = outbox;
        this.optOuts = optOuts;
        this.worker = worker;
        this.props = props;
        this.work = work;
        this.msg = msg;
        this.audit = audit;
    }

    @ModelAttribute
    void common(Model m) {
        m.addAttribute("channels", Channel.values());
        m.addAttribute("smsDryRun", gateway.dryRun(Channel.SMS));
        m.addAttribute("waDryRun", gateway.dryRun(Channel.WHATSAPP));
        m.addAttribute("silence", service.silencePeriod().orElse(null));
    }

    private static ResponseStatusException notFound() { return new ResponseStatusException(HttpStatus.NOT_FOUND); }

    // ---------------------------------------------------------------- compose
    /** Opened from the voter list (with its filters) or from a voter's page. */
    @GetMapping("/compose")
    public String compose(@ModelAttribute("f") VoterFilter f, @RequestParam(defaultValue = "SMS") Channel channel,
                          @RequestParam(required = false) Long templateId, Model m) {
        f.setAssemblyNo(work.restrict(f.getAssemblyNo()));
        List<MessageTemplate> list = templates.findByChannelAndActiveTrueOrderByLanguageAscNameAsc(channel);
        MessageTemplate t = templateId == null ? null : list.stream().filter(x -> x.getId().equals(templateId)).findFirst().orElse(null);
        if (t == null) {
            String lang = Languages.of(org.springframework.context.i18n.LocaleContextHolder.getLocale()).code();
            t = list.stream().filter(x -> x.getLanguage().equals(lang)).findFirst().orElse(list.isEmpty() ? null : list.get(0));
        }
        m.addAttribute("channel", channel);
        m.addAttribute("templates", list);
        m.addAttribute("template", t);
        m.addAttribute("ready", gateway.ready(channel));
        m.addAttribute("dryRun", gateway.dryRun(channel));
        if (t != null) m.addAttribute("preview", service.preview(f, t));
        return "messages/compose";
    }

    @PostMapping("/campaigns")
    public String create(@ModelAttribute("f") VoterFilter f, @RequestParam Long templateId, @RequestParam(name = "campaignName", required = false) String name,
                         @RequestParam(required = false) @org.springframework.format.annotation.DateTimeFormat(iso = org.springframework.format.annotation.DateTimeFormat.ISO.DATE_TIME) LocalDateTime scheduledAt,
                         @RequestParam(required = false) String summary, RedirectAttributes ra) {
        f.setAssemblyNo(work.restrict(f.getAssemblyNo()));
        MessageTemplate t = templates.findById(templateId).orElseThrow(MessagingController::notFound);
        try {
            Campaign c = service.create(name, t, f, summary, scheduledAt, AuditService.currentUser());
            ra.addFlashAttribute("success", "msg.flash.queued");
            return "redirect:/messages/campaigns/" + c.getId();
        } catch (IllegalStateException e) {
            ra.addFlashAttribute("error", e.getMessage());
            return "redirect:/messages/compose?" + f.queryWithSort() + "&channel=" + t.getChannel() + "&templateId=" + t.getId();
        }
    }

    // ---------------------------------------------------------------- campaigns
    @GetMapping({"", "/campaigns"})
    public String campaigns(@RequestParam(defaultValue = "0") int page, Model m) {
        m.addAttribute("page", campaigns.findAllByOrderByIdDesc(PageRequest.of(Math.max(0, page), 25)));
        return "messages/campaigns";
    }

    @GetMapping("/campaigns/{id}")
    public String campaign(@PathVariable Long id, @RequestParam(required = false) OutboxMessage.Status status,
                           @RequestParam(defaultValue = "0") int page, Model m) {
        Campaign c = campaigns.findById(id).orElseThrow(MessagingController::notFound);
        m.addAttribute("c", c);
        m.addAttribute("status", status);
        m.addAttribute("statuses", OutboxMessage.Status.values());
        PageRequest pr = PageRequest.of(Math.max(0, page), 50);
        m.addAttribute("page", status == null ? outbox.findByCampaignIdOrderByIdAsc(id, pr)
                : outbox.findByCampaignIdAndStatusOrderByIdAsc(id, status, pr));
        m.addAttribute("windowOpen", service.insideSendWindow());
        m.addAttribute("sendFrom", props.getSendFrom());
        m.addAttribute("sendUntil", props.getSendUntil());
        return "messages/campaign";
    }

    @PostMapping("/campaigns/{id}/cancel")
    public String cancel(@PathVariable Long id, RedirectAttributes ra) {
        Campaign c = campaigns.findById(id).orElseThrow(MessagingController::notFound);
        service.cancel(c, AuditService.currentUser());
        ra.addFlashAttribute("success", "msg.flash.cancelled");
        return "redirect:/messages/campaigns/" + id;
    }

    // ---------------------------------------------------------------- templates (ADMIN)
    @PreAuthorize("hasAuthority('MESSAGE_ADMIN')")
    @GetMapping("/templates")
    public String templates(Model m) {
        m.addAttribute("items", templates.findAllByOrderByChannelAscLanguageAscNameAsc());
        return "messages/templates";
    }

    @PreAuthorize("hasAuthority('MESSAGE_ADMIN')")
    @GetMapping({"/templates/new", "/templates/{id}/edit"})
    public String templateForm(@PathVariable(required = false) Long id, Model m) {
        m.addAttribute("item", id == null ? new MessageTemplate() : templates.findById(id).orElseThrow(MessagingController::notFound));
        templateFormData(m);
        return "messages/template-form";
    }

    private void templateFormData(Model m) {
        m.addAttribute("placeholders", TemplateRenderer.PLACEHOLDERS.keySet());
        m.addAttribute("langs", Languages.ALL);
    }

    @PreAuthorize("hasAuthority('MESSAGE_ADMIN')")
    @PostMapping("/templates")
    public String saveTemplate(@Valid @ModelAttribute("item") MessageTemplate t, BindingResult br, Model m, RedirectAttributes ra) {
        List<String> bad = TemplateRenderer.unknownPlaceholders(t.getBody(), t.getParams());
        if (!bad.isEmpty()) br.rejectValue("body", "msg.error.placeholder", new Object[]{String.join(", ", bad)}, null);
        if (br.hasErrors()) {
            templateFormData(m);
            return "messages/template-form";
        }
        boolean isNew = t.getId() == null;
        templates.save(t);
        audit.log(isNew ? "CREATE" : "UPDATE", "TEMPLATE", t.getId(), t.getChannel() + " " + t.getName());
        ra.addFlashAttribute("success", "flash.saved");
        return "redirect:/messages/templates";
    }

    @PreAuthorize("hasAuthority('MESSAGE_ADMIN')")
    @PostMapping("/templates/{id}/delete")
    public String deleteTemplate(@PathVariable Long id, RedirectAttributes ra) {
        MessageTemplate t = templates.findById(id).orElseThrow(MessagingController::notFound);
        if (campaigns.existsByTemplateId(id)) {          // keep history: deactivate instead
            t.setActive(false);
            templates.save(t);
            ra.addFlashAttribute("success", "msg.flash.deactivated");
        } else {
            templates.delete(t);
            ra.addFlashAttribute("success", "flash.deleted");
        }
        audit.log("DELETE", "TEMPLATE", id, t.getName());
        return "redirect:/messages/templates";
    }

    // ---------------------------------------------------------------- opt-outs (ADMIN)
    @PreAuthorize("hasAuthority('MESSAGE_ADMIN')")
    @GetMapping("/optouts")
    public String optOuts(Model m) {
        m.addAttribute("items", optOuts.findAllByOrderByCreatedAtDesc());
        return "messages/optouts";
    }

    /** Operators may record an opt-out (e.g. from a voter's page) - removing one is ADMIN only. */
    @PostMapping("/optouts")
    public String addOptOut(@RequestParam String mobile, @RequestParam(required = false) Channel channel,
                            @RequestParam(required = false) String reason, @RequestParam(required = false) String back,
                            RedirectAttributes ra) {
        boolean ok = service.optOut(mobile, channel, reason, AuditService.currentUser());
        ra.addFlashAttribute(ok ? "success" : "error", ok ? "msg.flash.optedOut" : "validation.mobile");
        return "redirect:" + (back != null && back.startsWith("/") && !back.startsWith("//") ? back : "/messages/optouts");
    }

    @PreAuthorize("hasAuthority('MESSAGE_ADMIN')")
    @PostMapping("/optouts/{id}/delete")
    public String deleteOptOut(@PathVariable Long id, RedirectAttributes ra) {
        optOuts.findById(id).ifPresent(o -> {
            optOuts.delete(o);
            audit.log("DELETE", "OPTOUT", id, null);
        });
        ra.addFlashAttribute("success", "flash.deleted");
        return "redirect:/messages/optouts";
    }

    // ---------------------------------------------------------------- settings (ADMIN)
    @PreAuthorize("hasAuthority('MESSAGE_ADMIN')")
    @GetMapping("/settings")
    public String settings(Model m) {
        m.addAttribute("props", props);
        m.addAttribute("smsProvider", props.getSmsProvider());
        m.addAttribute("waProvider", props.getWhatsappProvider());
        m.addAttribute("smsReady", gateway.ready(Channel.SMS));
        m.addAttribute("waReady", gateway.ready(Channel.WHATSAPP));
        m.addAttribute("windowOpen", service.insideSendWindow());
        m.addAttribute("templatesSms", templates.findByChannelAndActiveTrueOrderByLanguageAscNameAsc(Channel.SMS));
        m.addAttribute("templatesWa", templates.findByChannelAndActiveTrueOrderByLanguageAscNameAsc(Channel.WHATSAPP));
        return "messages/settings";
    }

    /** Sends one message right away (ignores the window, not the silence period) to check the provider. */
    @PreAuthorize("hasAuthority('MESSAGE_ADMIN')")
    @PostMapping("/settings/test")
    public String test(@RequestParam String mobile, @RequestParam Long templateId, RedirectAttributes ra) {
        MessageTemplate t = templates.findById(templateId).orElseThrow(MessagingController::notFound);
        String e = service.e164(mobile);
        if (e == null) {
            ra.addFlashAttribute("error", "validation.mobile");
        } else if (service.silencePeriod().isPresent()) {
            ra.addFlashAttribute("error", "msg.error.silence");
        } else {
            String body = t.getBody();
            SendResult r = gateway.send(t.getChannel(), e, body, t, List.of());
            audit.log("MSG_TEST", "TEMPLATE", t.getId(), MessagingService.mask(e) + " " + (r.success() ? "ok " + r.providerId() : r.error()));
            ra.addFlashAttribute("testResult", r.success() ? msg.get("msg.test.ok") + " " + r.providerId() : msg.get("msg.test.failed") + " " + r.error());
        }
        return "redirect:/messages/settings";
    }
}
