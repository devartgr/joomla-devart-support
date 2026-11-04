# Guest tickets — prerequisites (not implemented)

Confirmed product interest after Action Log + frontend attachments.
Do **not** implement until these points are explicitly approved.

## Proposed auth model (Comments-style)

1. Guest enters name + email on the portal.
2. Email OTP (hashed at rest, rate limit + cooldown).
3. Short-lived guest session after verify.
4. Session may create tickets, list tickets for that email, and reply to those tickets.
5. Tickets stored with `user_id = 0`, `requester_email` / `requester_name` from verified session.

## Decisions still required

| Topic | Question | Suggested default |
|---|---|---|
| Auth | Email OTP + session vs magic link per ticket | OTP + session (like DevArt Comments) |
| Menu / ACL | Public menu item vs Registered-only + separate guest entry | Dedicated guest portal routes; Access Public only for OTP/guest screens |
| Attachments | Same FE rules, stricter, or off by default for guests | Off by default (`attachments_guest_enabled`) |
| Privacy | Export/erase guest-by-email; OTP/session out of export | Mirror Comments privacy scope notes |
| Mail | OTP mail + staff notify; requester copies | Same Email Settings as registered where applicable |
| Account merge | Later Joomla login with same email | Keep separate until an explicit merge milestone |
| Rate limits | OTP send/verify abuse | Cooldown + per-IP/email limits |

## Explicitly out of this feature

- Internal staff assignment
- Modules
- Live chat

## Gate

Implementation starts only after written confirmation of the table above.
