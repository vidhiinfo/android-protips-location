package com.vidhi.voters.config;

import org.springframework.stereotype.Component;
import org.springframework.web.context.annotation.SessionScope;

import java.io.Serializable;

/**
 * The assembly chosen on the login screen. While set, every screen (voters, exports, reports, results)
 * is limited to this assembly. null = all assemblies (only for users not assigned to an assembly).
 */
@Component
@SessionScope
public class WorkContext implements Serializable {
    private Integer assemblyNo;
    private String assemblyName;

    public Integer getAssemblyNo() { return assemblyNo; }
    public String getAssemblyName() { return assemblyName; }
    public boolean isScoped() { return assemblyNo != null; }

    public void set(Integer no, String name) {
        this.assemblyNo = no;
        this.assemblyName = name;
    }

    /** The assembly a request may use: the session's when scoped, otherwise whatever was asked for. */
    public Integer restrict(Integer requested) { return assemblyNo != null ? assemblyNo : requested; }

    public boolean allows(Integer assembly) { return assemblyNo == null || assemblyNo.equals(assembly); }
}
