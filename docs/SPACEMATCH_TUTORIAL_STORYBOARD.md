# SpaceMatch Tutorial Storyboard

**Status:** Storyboard only. No MP4 or WebVTT file has been produced.  
**Target runtime:** Approximately 3 minutes.  
**Capture environment:** Local, isolated browser fixtures only. Do not record real renter, owner, or administrator personal data.

## Production Direction

Use the current SpaceMatch interface and its navy/blue visual identity. Keep cursor movement deliberate, hold on important form labels, and use brief callouts rather than covering listing details. Record desktop actions at 1440px and include a short mobile browse shot at 390px. Use only generated QA accounts and listings from the dedicated browser fixture database. Do not record passwords, reset tokens, personal data, or production-like records.

The narration should be warm, clear, and measured at approximately 125-135 words per minute. Captions should be synchronized sentence-by-sentence, with high contrast and no more than two lines at once. Do not autoplay audio. The app onboarding currently offers a text tour because no narration/video asset is available.

Reference captures from the actual application and isolated generated listings are available: [homepage welcome](browser/tutorial-first-visit.png), [public search](browser/public-search-desktop.png), [public details](browser/public-detail-tablet.png), [owner dashboard](browser/owner-dashboard-desktop.png), [owner property](browser/owner-property-desktop.png), and [admin property review](browser/admin-property-audit-desktop.png). These are still screenshots, not a screen recording.

## Scenes

| Scene | Target time | Verified visuals | Production notes |
|---|---:|---|---|
| 1. Welcome | 0:00-0:18 | SpaceMatch homepage, logo, live featured spaces or the real empty state | Open on the brand, then show the actual homepage. Do not insert invented listings or statistics. |
| 2. Create an account | 0:18-0:42 | Registration role choices, validation-safe form, login and role dashboard | Show renter/owner account types only. Use a QA account; never expose its credentials. |
| 3. Explore spaces | 0:42-1:14 | Browse Properties, location, price, property type, amenities, capacity, sorting | Show one search and its actual URL-backed filters. Keep query parameters readable. |
| 4. Compare a listing | 1:14-1:42 | Property images, monthly price, published spaces, amenities, capacity and location | Show only public published/available listing details. Do not imply contact or booking actions exist. |
| 5. Favorites and inquiries | Excluded | Not implemented | Do not record or narrate this scene until favorites and the inquiry/response workflow exist. |
| 6. Viewing and reservation requests | Excluded | Not implemented | Do not show a request form, approval, or confirmation. Existing bookings do not provide this workflow. |
| 7. Owner workspace | 1:42-2:26 | Owner dashboard, create property, add space, amenity selection, image upload, publish | Use generated QA data. Show ownership-scoped property management and the real publish action. |
| 8. Managing renter requests | Excluded | Not implemented | Inquiries, viewings, reservations, conversations, and notification workflows are absent. |
| 9. Administrator overview | 2:26-2:50 | Admin dashboard, property search/detail, moderation reason and recorded status | Use isolated fixtures only. Avoid showing real user information or internal security settings. |
| 10. Closing | 2:50-3:08 | Return to homepage, logo, Browse Properties call to action | End with an implemented browse action. Do not promise favorites or owner-renter messaging. |

Scenes 5, 6, and 8 are intentionally omitted from the video-ready script until their application features are implemented and tested. The first-visit text tour similarly shows only discovery, account/profile, owner listing management, and administrator property moderation.

## Asset Checklist

- Screen recording: not produced.
- AI voice track: not produced; no approved narration-generation service is available in this environment.
- MP4: not produced; no placeholder media is referenced by the application.
- WebVTT captions: not produced; create after final voice timing is known.
- Onboarding UI: implemented as an accessible text-based tour with an explicit no-video notice.
- Browser screenshots: [first-visit welcome](browser/tutorial-first-visit.png), [mobile text tour](browser/tutorial-tour-mobile.png), and the scene-reference captures listed above.