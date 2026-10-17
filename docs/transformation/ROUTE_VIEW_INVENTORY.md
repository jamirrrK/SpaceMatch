# Complete route-to-view inventory

| Method | URI | Access | View / response |
| --- | --- | --- | --- |
| GET / HEAD | `/` | Public | home |
| GET / HEAD | `/admin/dashboard` | Verified administrator | admin.dashboard |
| GET / HEAD | `/admin/properties` | Verified administrator | admin.properties.index |
| GET / HEAD | `/admin/properties/{property}` | Verified administrator | admin.properties.show |
| PATCH | `/admin/properties/{property}/status` | Verified administrator | Redirect / validated mutation |
| GET / HEAD | `/bookings` | Verified account; role-scoped data | bookings.index |
| GET / HEAD | `/dashboard` | Verified account; role-scoped data | Redirect / validated mutation |
| GET / HEAD | `/forgot-password` | Guest | auth.forgot-password |
| POST | `/forgot-password` | Guest | Redirect / validated mutation |
| GET / HEAD | `/login` | Guest | auth.login |
| POST | `/login` | Guest | Redirect / validated mutation |
| POST | `/logout` | Authenticated; pending accounts allowed | Redirect / validated mutation |
| GET / HEAD | `/maintenance` | Verified account; role-scoped data | maintenance.index |
| GET / HEAD | `/owner/dashboard` | Verified property owner; ownership policies | owner.dashboard |
| PATCH | `/owner/images/{image}` | Verified property owner; ownership policies | Redirect / validated mutation |
| DELETE | `/owner/images/{image}` | Verified property owner; ownership policies | Redirect / validated mutation |
| GET / HEAD | `/owner/properties` | Verified property owner; ownership policies | owner.properties.index |
| POST | `/owner/properties` | Verified property owner; ownership policies | Redirect / validated mutation |
| GET / HEAD | `/owner/properties/create` | Verified property owner; ownership policies | owner.properties.create |
| GET / HEAD | `/owner/properties/{property}` | Verified property owner; ownership policies | owner.properties.show |
| PUT|PATCH | `/owner/properties/{property}` | Verified property owner; ownership policies | Redirect / validated mutation |
| GET / HEAD | `/owner/properties/{property}/edit` | Verified property owner; ownership policies | owner.properties.edit |
| POST | `/owner/properties/{property}/images` | Verified property owner; ownership policies | Redirect / validated mutation |
| PATCH | `/owner/properties/{property}/publish` | Verified property owner; ownership policies | Redirect / validated mutation |
| GET / HEAD | `/owner/properties/{property}/spaces` | Verified property owner; ownership policies | owner.spaces.index |
| PATCH | `/owner/properties/{property}/status` | Verified property owner; ownership policies | Redirect / validated mutation |
| GET / HEAD | `/owner/spaces` | Verified property owner; ownership policies | owner.spaces.index |
| POST | `/owner/spaces` | Verified property owner; ownership policies | Redirect / validated mutation |
| GET / HEAD | `/owner/spaces/create` | Verified property owner; ownership policies | owner.spaces.create |
| PUT|PATCH | `/owner/spaces/{space}` | Verified property owner; ownership policies | Redirect / validated mutation |
| GET / HEAD | `/owner/spaces/{space}/edit` | Verified property owner; ownership policies | owner.spaces.edit |
| PATCH | `/owner/spaces/{space}/publish` | Verified property owner; ownership policies | Redirect / validated mutation |
| GET / HEAD | `/payments` | Verified account; role-scoped data | payments.index |
| GET / HEAD | `/profile` | Verified account; role-scoped data | profile |
| PATCH | `/profile` | Verified account; role-scoped data | Redirect / validated mutation |
| PATCH | `/profile/password` | Verified account; role-scoped data | Redirect / validated mutation |
| GET / HEAD | `/properties` | Public | properties.index |
| GET / HEAD | `/properties/{property}` | Public | properties.show |
| GET / HEAD | `/property-images/{image}` | Public | Protected image response |
| GET / HEAD | `/register` | Guest | auth.register |
| POST | `/register` | Guest | Redirect / validated mutation |
| GET / HEAD | `/renter/dashboard` | Verified renter | renter.dashboard |
| POST | `/reset-password` | Guest | Redirect / validated mutation |
| GET / HEAD | `/reset-password/{token}` | Guest | auth.reset-password |
| GET / HEAD | `/reviews` | Verified account; role-scoped data | reviews.index |
| GET / HEAD | `/spaces` | Public | Redirect / validated mutation |
| GET / HEAD | `/spaces/{space}` | Public | Redirect / validated mutation |
| GET / HEAD | `/verify-contact` | Authenticated; pending accounts allowed | auth.verify-contact |
| POST | `/verify-contact` | Authenticated; pending accounts allowed | Redirect / validated mutation |
| POST | `/verify-contact/resend` | Authenticated; pending accounts allowed | Redirect / validated mutation |

System errors: branded 403, 404, 419, 429, 500, 503 via Laravel error rendering. Unrouted welcome.blade.php is excluded. Amenities/images are embedded management sections. No messaging, favorites, booking creation, checkout, notification or legal policy route is invented.
