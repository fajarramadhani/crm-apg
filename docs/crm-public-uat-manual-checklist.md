# CRM Public Manual UAT Checklist

Test at `375x812`, `768x1024`, and `1366x768`, plus 200% browser zoom.

## Public Submission

- Complete valid submission and verify receipt/tracking link.
- Trigger every required-field error and verify focus/labels.
- Upload valid files and reject executable, double-extension, oversized, and excessive files.
- Double-click submit and verify one ticket/attachment set.

## Public Tracking and History

- Test valid, malformed, expired, revoked, and rotated tracking links.
- Verify timeline and requester updates contain no internal data.
- Complete history email/cabang, OTP, resend cooldown, empty state, pagination, expiry, and session end.
- Confirm old links no longer open after rotation/revocation.

## UAT and Confirmation

- Verify action buttons only appear at backend-approved statuses.
- Complete OTP accepted/rejected flows and required notes.
- Upload UAT evidence and retry the same request.
- Test wrong/expired OTP, rate limit, session expiry, status conflict, double click, offline/500 response, and tracking rotation while the dialog is open.
- Verify action success refreshes tracking/timeline and removes the action button.
- Verify keyboard Tab/Shift+Tab remains inside dialogs, Escape closes when not submitting, and focus returns to the launcher.

## Supervisor Access Panel

- Verify permission and public-ticket gating.
- Test active, expired, revoked, and unrecoverable-key states.
- Copy link with Clipboard API allowed and denied.
- Issue, rotate, revoke, dialog confirmation, duplicate clicks, and API failures.
- Verify audit actor label `Requester Publik Terverifikasi` and no credential material.

## Operations and Security

- Inspect browser history, frontend/proxy logs, APM, analytics, and exception monitoring for tokens, OTP, email, and Authorization headers.
- Verify frontend `/track/*` responses carry no-referrer/no-store/noindex headers at the reverse proxy.
- Verify private attachment storage cannot be accessed via `/storage`.
- Run all cleanup commands twice and confirm active credentials remain while expired records are removed.
