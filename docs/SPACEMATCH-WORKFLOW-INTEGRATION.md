# SpaceMatch Workflow Integration

## Verified renter and owner workflow

### Registration, verification, and discovery
1. A renter registers through the student flow and completes the existing single-use OTP verification.
2. Renter preferences drive discovery filters; renters can save and compare real spaces.
3. The homepage carousel shows published, available listing images and provides keyboard-operable controls with reduced-motion support.

### Inquiry and viewing
1. A renter creates an inquiry on a listed space.
2. The owner and renter exchange replies in one participant-authorized conversation. AJAX replies are polled and new messages update unread counts.
3. A renter requests a viewing; the owner schedules it and the renter sees the resulting activity.
4. Inquiry polling and reply routes validate participation; messaging requests are throttled.

### Rental request and booking
1. The renter accepts the rental terms and submits a request.
2. The owner can request additional information, reject, or approve the request.
3. Approval creates the booking and unpaid schedule. The booking stores the accepted terms snapshot.
4. The renter and owner coordinate move-in in the conversation. Owners can mark a booking active only on or after its move-in date and complete it only on or after its end date.

### Payments and receipts
1. An owner may record manual receipts against the ledger.
2. A renter may submit amount, date, method, optional reference, and private evidence for an outstanding payment.
3. An authorized owner/admin reviews the evidence. Verification transactionally updates the ledger, creates a payment transaction/receipt, and records an audit event; rejection requires a reason.
4. Private evidence is served only through an authorization-checked route. The application does not process card, wallet, or bank payments.

### Maintenance and reviews
1. A renter submits a maintenance issue with optional evidence.
2. An owner records action/updates; the renter can see the resolution history.
3. A renter can review an eligible completed rental. An administrator can moderate a review while retaining audit history.

### Administration and Master Administrator
- Regular admin workflows include user and review moderation/audit.
- Only a Master Administrator can grant or revoke the Master Administrator role through the dedicated workspace. Promotion requires a verified account; self-demotion is blocked. Role changes are audited.
- The CLI provisioning command promotes an existing administrator account interactively; it does not create a new user.

## Browser validation
The isolated Playwright workflow passed 16 scenarios using a fresh SQLite database, synthetic OTP sink, and synthetic accounts. It verified renter registration through review moderation, payment proof verification, Master Administrator permissions, carousel interaction, keyboard navigation, and no horizontal overflow at 375, 768, 1366, or 1920 px. No JavaScript errors or failed responses were recorded.

## Boundaries and remaining integration checks
- Tests do not verify real email/SMS delivery, MySQL concurrency, hosting-specific filesystem behavior, or production configuration.
- The payment-submission schema is an additive migration that has not been applied to the existing local MySQL database or production.
- Production deployment requires a verified Composer dependency bundle for the exact PHP/extensions and a separately reviewed migration/backup procedure.
- Production `.env`, database, uploads, and hosting were not accessed or changed.
