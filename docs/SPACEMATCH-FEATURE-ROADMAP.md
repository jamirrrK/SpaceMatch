# SpaceMatch Feature Roadmap

## Current baseline
The project already includes a broad and meaningful feature set for a Philippine rental marketplace, with real route definitions and domain models for:
- authentication and OTP verification
- renter and owner account flows
- property and space discovery
- saved properties and saved spaces
- inquiry and viewing workflows
- rental request processing
- booking lifecycle and payment tracking
- maintenance requests and reviews
- notifications and user profile management
- admin moderation and reporting

## Priority order
### Priority 1 — Stability and correctness
1. Resolve migration/schema drift and preserve migration history
2. Validate role-based access and contact verification flow
3. Ensure every dashboard reads from real controller data contracts
4. Confirm notifications and audit flows are consistent

### Priority 2 — End-to-end workflows
1. Renter → property discovery → inquiry → owner reply
2. Renter → viewing request → owner decision → follow-up reschedule or confirmation
3. Renter → rental request → owner review → booking → payment → tenant lifecycle
4. Owner → maintenance and report tracking

### Priority 3 — UX polish
1. Responsive dashboard layouts
2. Empty states and validation messaging
3. Philippine wallet and formatting standards
4. More explicit mobile-first flows for search and booking

### Priority 4 — Operational readiness
1. Admin reporting and moderation workflow review
2. Backup and rollback planning
3. Deployment automation and environment verification
4. Production smoke tests

## Recommended delivery sequence
1. Finalize migration integrity and schema checks
2. Validate all feature tests on the sqlite test database
3. Run targeted local MySQL validation under controlled conditions
4. Prepare deployment docs and production checklist
5. Submit a merge-ready PR into `main` after approval

## Key known working features
- User registration and role selection
- Contact verification and OTP challenge issuance
- Saved spaces and saved properties
- Rental requests and inquiry handling
- Viewing appointments and activity tracking
- Owner property/space management
- Dashboard metrics for renter and owner

## Remaining watch items
- Admin panel completeness vs. owner/renter flows
- Production environment configuration for OTP delivery
- Deployment compatibility with cPanel and public path structure
- Final consistency checks across notifications, audit events, and reporting
