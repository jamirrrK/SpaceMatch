<?php

namespace App\Http\Controllers;

class LegalController extends Controller
{
    public function terms()
    {
        return view('legal.document', ['title' => 'Terms of Service', 'version' => config('marketplace.terms_version'), 'sections' => [
            'Platform role' => 'SpaceMatch connects renters and owners. Owners supply listings and decide rental applications. SpaceMatch is not the landlord, a guarantor of a listing, or an automated payment processor.',
            'Account eligibility' => 'Registration is limited to adults aged 18 or older who can enter rental arrangements. Minor accounts are not supported until appropriate legal, consent and safety safeguards have been approved. Provide accurate details and protect your password and verification codes.',
            'Renter responsibilities' => 'Review actual photos, costs, rules and location. Ask questions and arrange a safe viewing. Do not submit false applications, payment evidence or reviews.',
            'Owner responsibilities and listing accuracy' => 'Publish only spaces you are authorized to offer, with genuine photos, accurate availability, prices and terms. Disclose costs and restrictions. Owner-reported locations and school distances are not independently verified.',
            'Rental requests and transactions' => 'A request is not a guaranteed reservation. Review and accept the disclosed terms. Recorded payments and proof submissions require manual owner review and are not bank or gateway confirmation.',
            'Prohibited activity' => 'Fraud, discriminatory or unlawful listings, harassment, impersonation, unauthorized access, misuse of personal data and manipulation of reviews are prohibited.',
            'Moderation and account suspension' => 'Authorized administrators may moderate listings and reviews and investigate misuse. Access restrictions require an authorized process; this draft does not imply every restriction tool is already available. Keep evidence and contact support about disputed decisions.',
            'Disputes and complaints' => 'Keep records and first raise rental concerns with the other party where safe. Send platform complaints to the configured support contact. Legal remedies and appropriate public authorities remain available.',
            'Service limitations' => 'Availability, prices and owner statements may change. The platform can experience outages and does not guarantee every listing. These terms do not remove rights or duties that applicable law requires.',
            'Updates' => 'Material changes will be identified by a new version and effective date. Reacceptance and notices must be reviewed by the operator before updated terms take effect.',
        ]]);
    }

    public function privacy()
    {
        return view('legal.document', ['title' => 'Privacy Notice', 'version' => config('marketplace.privacy_version'), 'sections' => [
            'Information collected' => 'Account name, contact details, protected password, role, optional preferences, school/campus information, owner organization details, listing details and photos, messages, applications, rental records, submitted payment evidence, reviews and security/audit records are processed as needed for the features you use. Do not upload unnecessary sensitive information.',
            'Purposes and account verification' => 'Information supports accounts, contact verification, rental discovery, communication, applications, rental management, manual payment review, safety and abuse investigation. Verification codes expire and delivery depends on the configured email or SMS provider. Optional marketing requires separate consent and is not enabled by this registration form.',
            'Property and location information' => 'Owners choose whether to show an exact owner-confirmed pin, an approximate area or no pin. Exact street details and precise coordinates are withheld publicly when private. Listing photos can reveal identifying details; owners should review photos before publishing.',
            'Third-party services' => 'Loading the map view may send browser and IP information to the configured OpenStreetMap-compatible tile provider under that provider\'s own terms. Maps load only when the user chooses to display them. Email and SMS providers process delivery information when configured. No external geocoding service is called by this integration.',
            'Sharing and access' => 'Public listings and permitted reviews are visible to visitors. Rental participants and authorized administrators receive only information needed for their workflows. Service providers may process information for delivery or hosting. The operator must review lawful bases, processor agreements and any international transfers before launch.',
            'Security' => 'Role-based authorization, protected password hashing, private evidence storage, validation and audit logs reduce risks. No system is risk-free. Do not send identity documents, bank secrets or verification codes in public listings or reviews.',
            'Retention' => 'Keep records only as necessary for service, disputes, security and applicable obligations. A final retention schedule and deletion/request process require operator/legal approval; no specific unsupported retention period is promised here.',
            'Your rights' => 'Subject to applicable conditions, Philippine privacy law provides rights to information, access, correction, objection, erasure or blocking, data portability, complaint and damages. Contact the configured privacy channel to make a request; identity may need to be verified without collecting excessive data. Complaints may also be raised with the National Privacy Commission.',
            'Philippine requirements and updates' => 'This draft references Republic Act No. 10173 and National Privacy Commission guidance. The actual controller identity, lawful processing bases, retention schedule, rights handling, incident response and contacts must be reviewed before launch. Version changes will be published with effective dates.',
        ]]);
    }
}
