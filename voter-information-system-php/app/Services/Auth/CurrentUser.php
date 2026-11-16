<?php

namespace App\Services\Auth;

/**
 * The logged-in user, kept in the session: username, full name, role codes, permission codes,
 * assigned assembly (lock) and the working assembly chosen on the login page.
 */
class CurrentUser
{
    public const KEY = 'vis.user';

    public function data(): ?array { return session(self::KEY); }
    public function check(): bool { return $this->data() !== null; }
    public function username(): ?string { return $this->data()['username'] ?? null; }
    public function fullName(): ?string { return $this->data()['full_name'] ?? null; }
    public function permissions(): array { return $this->data()['permissions'] ?? []; }
    public function can(string $p): bool { return in_array($p, $this->permissions(), true); }

    /** Assembly chosen at login: every screen is limited to it. null = all assemblies. */
    public function workAssembly(): ?int { return $this->data()['work_assembly'] ?? null; }
    public function workAssemblyName(): ?string { return $this->data()['work_assembly_name'] ?? null; }
    public function scoped(): bool { return $this->workAssembly() !== null; }

    /** The assembly a request may use: the session's when scoped, otherwise what was asked for. */
    public function restrict($requested): ?int
    {
        if ($this->scoped()) return $this->workAssembly();
        return $requested === null || $requested === '' ? null : (int) $requested;
    }

    /** Worker record linked to this login: such a user sees and edits only the voters assigned to them (admins are never limited). */
    public function worker(): ?\App\Models\Worker
    {
        return $this->check() && !$this->can('USER_ADMIN') ? \App\Models\Worker::forUsername($this->username()) : null;
    }
    public function workerId(): ?int { return $this->worker()?->id; }
    public function allowsVoter($v): bool { return $this->allows($v->assembly_no) && (($w = $this->workerId()) === null || (int) $v->worker_id === $w); }

    public function allows($assembly): bool { return !$this->scoped() || (int) $assembly === $this->workAssembly(); }

    public function login(array $data): void
    {
        session()->regenerate();
        session([self::KEY => $data]);
    }

    public function logout(): void
    {
        session()->invalidate();
        session()->regenerateToken();
    }

    /** First page the user may open. */
    public function landingPage(): string
    {
        foreach (['DASHBOARD_VIEW' => '/dashboard', 'VOTER_VIEW' => '/voters', 'REPORT_VIEW' => '/reports', 'RESULT_VIEW' => '/results',
                     'MESSAGE_SEND' => '/messages/campaigns', 'USER_ADMIN' => '/admin/users'] as $p => $url) {
            if ($this->can($p)) return $url;
        }
        return '/account/password';
    }
}
