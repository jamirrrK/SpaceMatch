# SpaceMatch Workflow Integration

## End-to-end workflow map

### 1. Registration and verification
- Guest visits `/register` and selects renter or owner
- User verifies email or SMS through OTP
- `App\Http\Controllers\AuthController` creates the user and dispatches verification
- `App\Services\OtpVerificationService` issues a challenge with expiry, lockout, and resend handling
- `App\Http\Controllers\VerificationController` completes verification

### 2. Post-login routing
- `redirectForRole()` routes admin, owner, and renter to the right dashboard
- Contact verification middleware prevents access to protected pages until verification is complete

### 3. Property and space discovery
- Public property listing and detail routes resolve listing and availability data
- Renter users can compare spaces and save properties/spaces
- Search, comparison, and listing pages should be validated against current property data contracts

### 4. Inquiry and viewing flow
- Renter can create inquiries against a published space
- Owner can reply, close, and manage inquiry state
- Renter can request a viewing appointment
- Owner can accept, decline, or reschedule
- Notifications and timelines should update consistently

### 5. Rental request and booking flow
- Renter creates a rental request against a space
- Owner reviews and updates request status
- Booking creation happens once a request is approved
- Related payments and maintenance flows continue from booking data

### 6. Payment and account lifecycle
- Payment history and receipts are available through protected authenticated pages
- Owners can confirm payments
- Payment ledger and outstanding balance calculations are used in dashboards

### 7. Maintenance and review lifecycle
- Maintenance requests are linked to bookings
- Owners may update status and respond to issue tracking
- Reviews remain connected to bookings and public listing visibility

### 8. Notifications and audits
- Activity events are surfaced through notifications and related audit data
- The backend already contains notification models and migration patterns for inquiry and viewing activity tracking

## Critical success criteria
- Every protected route is guarded by the correct middleware
- Every user action checks ownership or participant eligibility
- Every status transition is consistent with the application domain rules
- Public actions remain limited to published listings and valid participants

## Integration risk summary
The integration branch has strong route coverage and domain logic, but real database schema validation and targeted end-to-end tests remain the most important next step before production deployment.
