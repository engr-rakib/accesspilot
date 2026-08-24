# AccessPilot — The Journey (Client View)

> One request, from login to done — the way you experience it. No implementation talk, just what happens on the screen and the value at each step.

---

## The 60-second version

1. You log in with your portal account.
2. You search for a person, a mailbox or a server.
3. You press the button for what you need.
4. The portal does the work, shows you the result, and records it.
5. You copy the confirmation and move on.

Every one of those steps is secure, permission-checked and logged. That's the whole model — and it's why AccessPilot feels instant, not like a ticket queue.

---

## Stage 1 — Entering the Portal

- You arrive at the portal URL and **log in** (username + password).
- Forgot it? **Forgot Password** gets you back in safely. New to the team? **Register** applies for an account.
- The portal remembers you for the session and **auto-logouts after inactivity** — so a locked desk never becomes a security hole.
- If your IP is flagged, the portal refuses to even answer — attackers get a blank page, not a conversation.

## Stage 2 — Finding What You Need

- **Dashboard** greets you: quick actions, live monitoring, recent activity, notifications.
- **Assistant search** jumps to any page, any user, any mailbox by typing a few characters.
- Full pages are organised by job: Access Control, Employee Database, Exchange, Monitoring, Tools, Reports, Requests, Profile.

## Stage 3 — Doing the Work (the button moments)

This is where the application does the automation:

| You want to… | You press… | Minutes it takes |
|--------------|-----------|------------------|
| Create an employee account | **New User** → type employee ID → **Fetch** → **Create** | < 1 |
| Reset + unlock a locked account | **Reset + Unlock** (single or many IDs) | < 1 |
| Turn off a leaver | **Disable** | < 1 |
| Move someone to a new team | **Modify** → new OU/groups | < 2 |
| Make a new mailbox | **New Mailbox** → pick resource/room | < 2 |
| Give someone mailbox access | **Add Access** (full / send-as / calendar) | < 1 |
| See who owns an OU | **OU Report** → **Export CSV** | < 1 |
| Check if a server is healthy | open the node card → see CPU/MEM/Disk trends | < 1 |
| Prove who changed something | **User Activity** page | < 1 |

The result always comes back as a clear **action card** — colour-coded, with a summary (`Processed: N · Success: X · Skipped: Y · Failed: Z`), with a **copy button** for your records, and with per-user detail when you ran a bulk action.

## Stage 4 — Getting Granular (multi-ID, multi-results)

- Paste `user1, user2, user3` and the portal processes **each one** and reports per-user outcomes.
- Look up several people at once? You get **tabbed results** — one tab per person, each with full identity + HRMS detail.
- Typed the wrong ID? The portal suggests **near-matching IDs** so a typo becomes one extra click, not a failed request.

## Stage 5 — Knowing It's Recorded

- Every page you opened and every action you took is on the **audit trail** with the actor, time and outcome.
- Feature-level change logs preserve full transcripts per operation.
- Managers and auditors can answer "who did what, when, and what happened?" for any change in the company directory.

## Stage 6 — Self-Service & Requests

- Employees can raise **AD or Exchange requests** from the Request Portal (15 AD + 15 Exchange types) — no helpdesk call needed.
- Requests flow to an **approval queue** and, once approved, are executed by the automation.
- Approvers see everything, execute with one click, and the requester can track status.

## Stage 7 — Monitoring & Health (before problems become incidents)

- The **Dashboard and Monitoring** show host/container telemetry live.
- **Health checks** assess your AD forest and return an actionable report.
- **Diagnostics** ping, DNS, traceroute and email-analyse anything, from anywhere in the portal.
- If a license is expiring, the **notification centre** reminds you at 90/60/30 days.

## Stage 8 — Wrapping Up

- **Log out** — session, cookies and remember-me all cleaned up.
- Away from the desk? The **timeout watchdog** already logged you out before anyone could sit down.

---

## Journey summary in one picture

```
 Login → Find (search) → Do (button) → See (action card) → Record (audit) → Done
   ▲                                                    │
   └──────────────── next request ──────────────────────┘
```

Whatever the task — an account, a mailbox, a server, a report, a request — the journey is the same five beats. Simple to learn, impossible to lose.

© 2026 AccessPilot Engineering · All Rights Reserved