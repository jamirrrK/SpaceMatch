# Administrator Permissions Matrix

Authorization currently uses the existing `admin` and `master_admin` role values. Every administrator route additionally requires authentication and verified contact information. A first-login password-change flag restricts administrators to the password-change route and logout until they change the password.

| Capability | Admin | Master admin | Control |
|---|---:|---:|---|
| Platform dashboard | Yes | Yes | `role:admin,master_admin` |
| User directory | Read | Read | `role:admin,master_admin` |
| Property moderation | Yes | Yes | `role:admin,master_admin` |
| Booking oversight | Read | Read | `role:admin,master_admin` |
| Payment-submission review | Yes | Yes | `role:admin,master_admin` |
| Review moderation | Yes | Yes | `role:admin,master_admin` |
| Audit-log access | Read | Read | `role:admin,master_admin` |
| Change administrator roles | No | Yes | `role:master_admin`; verified account and last-master safeguards |
| Change administrator password | Own account | Own account | Current-password validation; audited |
| Provision a new master administrator | No | CLI operator only | Interactive Artisan command, hidden confirmed password, pending email verification |
| MFA / recovery administration | Not available | Not available | MFA has not been implemented |
| Suspend users / verify owners / manage disputes | Not available | Not available | Workflows have not been implemented |

This matrix describes current route-level capabilities, not a claim of fine-grained permissions. New administrative operations must have explicit server-side authorization and audit coverage before being exposed.
