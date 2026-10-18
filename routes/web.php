<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BookingLifecycleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\MaintenanceRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Owner\DashboardController as OwnerDashboardController;
use App\Http\Controllers\Owner\ReportController;
use App\Http\Controllers\Owner\TenantController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\PaymentSubmissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\PropertyImageController;
use App\Http\Controllers\RentalRequestController;
use App\Http\Controllers\Renter\DashboardController as RenterDashboardController;
use App\Http\Controllers\Renter\PreferenceController;
use App\Http\Controllers\Renter\SavedSpaceController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SavedPropertyController;
use App\Http\Controllers\SpaceComparisonController;
use App\Http\Controllers\SpaceController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\ViewingAppointmentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
Route::get('/properties/{property}', [PropertyController::class, 'show'])->name('properties.show');
Route::get('/spaces', [SpaceController::class, 'index'])->name('spaces.index');
Route::get('/spaces/{space}', [SpaceController::class, 'show'])->name('spaces.show');
Route::get('/property-images/{image}', [PropertyImageController::class, 'show'])->name('property-images.show');
Route::get('/compare', [SpaceComparisonController::class, 'index'])->name('spaces.compare.index');
Route::post('/compare/{space}', [SpaceComparisonController::class, 'store'])->name('spaces.compare.store');
Route::delete('/compare/{space}', [SpaceComparisonController::class, 'destroy'])->name('spaces.compare.destroy');

Route::middleware(['guest'])->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::get('/register/student', [AuthController::class, 'showRegistrationForm'])->name('student.register');
    Route::post('/register/student', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');

    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLinkEmail'])->name('password.email')->middleware('throttle:5,1');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.store')->middleware('throttle:5,1');
});

Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/verify-contact', [VerificationController::class, 'show'])->name('verification.notice');
    Route::post('/verify-contact', [VerificationController::class, 'verify'])->middleware('throttle:10,1')->name('verification.verify');
    Route::post('/verify-contact/resend', [VerificationController::class, 'resend'])->middleware('throttle:10,1')->name('verification.resend');
});

Route::middleware(['auth', 'contact.verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
});

Route::middleware(['auth', 'role:admin,master_admin', 'contact.verified'])->group(function () {
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::get('/admin/properties', [App\Http\Controllers\Admin\PropertyController::class, 'index'])->name('admin.properties.index');
    Route::get('/admin/properties/{property}', [App\Http\Controllers\Admin\PropertyController::class, 'show'])->name('admin.properties.show');
    Route::patch('/admin/properties/{property}/status', [App\Http\Controllers\Admin\PropertyController::class, 'updateStatus'])->name('admin.properties.status');
    Route::get('/admin/bookings', [App\Http\Controllers\Admin\BookingController::class, 'index'])->name('admin.bookings.index');
    Route::get('/admin/payment-submissions', [PaymentSubmissionController::class, 'index'])->name('admin.payments.submissions.index');
    Route::patch('/admin/payment-submissions/{submission}', [PaymentSubmissionController::class, 'review'])->name('admin.payments.submissions.review');
    Route::patch('/admin/reviews/{review}/moderation', [ReviewController::class, 'moderate'])->name('admin.reviews.moderate');
    Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
});

Route::middleware(['auth', 'role:master_admin', 'contact.verified'])->group(function () {
    Route::get('/admin/master-administrators', [App\Http\Controllers\Admin\MasterAdminController::class, 'index'])->name('admin.master-admins.index');
    Route::patch('/admin/master-administrators/{user}', [App\Http\Controllers\Admin\MasterAdminController::class, 'updateRole'])->name('admin.master-admins.update');
});

Route::middleware(['auth', 'role:owner', 'contact.verified'])->group(function () {
    Route::get('/owner/dashboard', [OwnerDashboardController::class, 'index'])->name('owner.dashboard');
    Route::get('/owner/inquiries', [InquiryController::class, 'ownerIndex'])->name('owner.inquiries.index');
    Route::get('/owner/inquiries/{inquiry}', [InquiryController::class, 'showOwner'])->name('owner.inquiries.show');
    Route::get('/owner/inquiries/{inquiry}/updates', [InquiryController::class, 'updates'])->name('owner.inquiries.updates');
    Route::post('/owner/inquiries/{inquiry}/replies', [InquiryController::class, 'reply'])->middleware('throttle:30,1')->name('owner.inquiries.reply');
    Route::patch('/owner/inquiries/{inquiry}/close', [InquiryController::class, 'close'])->name('owner.inquiries.close');

    Route::get('/owner/viewings', [ViewingAppointmentController::class, 'ownerIndex'])->name('owner.viewings.index');
    Route::get('/owner/viewings/{appointment}', [ViewingAppointmentController::class, 'showOwner'])->name('owner.viewings.show');
    Route::patch('/owner/viewings/{appointment}/accept', [ViewingAppointmentController::class, 'accept'])->name('owner.viewings.accept');
    Route::patch('/owner/viewings/{appointment}/decline', [ViewingAppointmentController::class, 'decline'])->name('owner.viewings.decline');
    Route::put('/owner/viewings/{appointment}/reschedule', [ViewingAppointmentController::class, 'reschedule'])->name('owner.viewings.reschedule');
    Route::get('/owner/tenants', [TenantController::class, 'index'])->name('owner.tenants.index');
    Route::get('/owner/reports', [ReportController::class, 'index'])->name('owner.reports.index');
    Route::get('/owner/reports/collections.csv', [ReportController::class, 'export'])->name('owner.reports.export');
    Route::get('/owner/rental-requests', [App\Http\Controllers\Owner\RentalRequestController::class, 'index'])->name('owner.requests.index');
    Route::patch('/owner/rental-requests/{rentalRequest}', [App\Http\Controllers\Owner\RentalRequestController::class, 'update'])->name('owner.requests.update');
    Route::patch('/owner/bookings/{booking}/status', [App\Http\Controllers\Owner\BookingController::class, 'update'])->name('owner.bookings.update');
    Route::patch('/owner/bookings/{booking}/cancel', [BookingLifecycleController::class, 'cancel'])->name('owner.bookings.cancel');
    Route::patch('/owner/bookings/{booking}/terminate', [BookingLifecycleController::class, 'terminate'])->name('owner.bookings.terminate');
    Route::patch('/owner/payments/{payment}/confirm', [App\Http\Controllers\Owner\PaymentController::class, 'confirm'])->name('owner.payments.confirm');
    Route::get('/owner/payment-submissions', [PaymentSubmissionController::class, 'index'])->name('owner.payments.submissions.index');
    Route::patch('/owner/payment-submissions/{submission}', [PaymentSubmissionController::class, 'review'])->name('owner.payments.submissions.review');
    Route::patch('/owner/maintenance/{maintenanceRequest}', [MaintenanceRequestController::class, 'update'])->name('owner.maintenance.update');
    Route::resource('/owner/properties', App\Http\Controllers\Owner\PropertyController::class)->except('destroy')->names('owner.properties');
    Route::patch('/owner/properties/{property}/status', [App\Http\Controllers\Owner\PropertyController::class, 'status'])->name('owner.properties.status');
    Route::patch('/owner/properties/{property}/publish', [App\Http\Controllers\Owner\PropertyController::class, 'publish'])->name('owner.properties.publish');
    Route::get('/owner/properties/{property}/spaces', [App\Http\Controllers\Owner\SpaceController::class, 'index'])->name('owner.properties.spaces');
    Route::resource('/owner/spaces', App\Http\Controllers\Owner\SpaceController::class)->except(['show', 'destroy'])->names('owner.spaces');
    Route::patch('/owner/spaces/{space}/publish', [App\Http\Controllers\Owner\SpaceController::class, 'publish'])->name('owner.spaces.publish');
    Route::post('/owner/properties/{property}/images', [App\Http\Controllers\Owner\PropertyImageController::class, 'store'])->name('owner.properties.images.store');
    Route::patch('/owner/images/{image}', [App\Http\Controllers\Owner\PropertyImageController::class, 'update'])->name('owner.images.update');
    Route::delete('/owner/images/{image}', [App\Http\Controllers\Owner\PropertyImageController::class, 'destroy'])->name('owner.images.destroy');
});

Route::middleware(['auth', 'role:renter', 'contact.verified'])->group(function () {
    Route::get('/renter/dashboard', [RenterDashboardController::class, 'index'])->name('renter.dashboard');
    Route::get('/renter/inquiries', [InquiryController::class, 'renterIndex'])->name('renter.inquiries.index');
    Route::post('/renter/spaces/{space}/inquiries', [InquiryController::class, 'start'])->middleware('throttle:10,1')->name('renter.inquiries.store');
    Route::get('/renter/inquiries/{inquiry}', [InquiryController::class, 'showRenter'])->name('renter.inquiries.show');
    Route::get('/renter/inquiries/{inquiry}/updates', [InquiryController::class, 'updates'])->name('renter.inquiries.updates');
    Route::post('/renter/inquiries/{inquiry}/replies', [InquiryController::class, 'reply'])->middleware('throttle:30,1')->name('renter.inquiries.reply');

    Route::get('/renter/viewings', [ViewingAppointmentController::class, 'renterIndex'])->name('renter.viewings.index');
    Route::post('/renter/spaces/{space}/viewings', [ViewingAppointmentController::class, 'request'])->name('renter.viewings.request');
    Route::get('/renter/viewings/{appointment}', [ViewingAppointmentController::class, 'showRenter'])->name('renter.viewings.show');
    Route::patch('/renter/viewings/{appointment}/confirm', [ViewingAppointmentController::class, 'confirmReschedule'])->name('renter.viewings.confirm');
    Route::patch('/renter/viewings/{appointment}/cancel', [ViewingAppointmentController::class, 'cancel'])->name('renter.viewings.cancel');

    Route::get('/renter/preferences', [PreferenceController::class, 'edit'])->name('renter.preferences.edit');
    Route::put('/renter/preferences', [PreferenceController::class, 'update'])->name('renter.preferences.update');

    Route::get('/renter/saved-spaces', [SavedSpaceController::class, 'index'])->name('renter.saved-spaces.index');
    Route::post('/renter/saved-spaces/{space}', [SavedSpaceController::class, 'store'])->name('renter.saved-spaces.store');
    Route::delete('/renter/saved-spaces/{space}', [SavedSpaceController::class, 'destroy'])->name('renter.saved-spaces.destroy');
    Route::get('/renter/saved-properties', [SavedPropertyController::class, 'index'])->name('renter.saved-properties.index');
    Route::post('/renter/saved-properties/{property}', [SavedPropertyController::class, 'store'])->name('renter.saved-properties.store');
    Route::delete('/renter/saved-properties/{property}', [SavedPropertyController::class, 'destroy'])->name('renter.saved-properties.destroy');
    Route::get('/renter/requests', [RentalRequestController::class, 'index'])->name('renter.requests.index');
    Route::post('/renter/requests/{space}', [RentalRequestController::class, 'store'])->name('renter.requests.store');
    Route::patch('/renter/requests/{rentalRequest}/response', [RentalRequestController::class, 'respond'])->name('renter.requests.respond');
    Route::patch('/renter/requests/{rentalRequest}/cancel', [RentalRequestController::class, 'cancel'])->name('renter.requests.cancel');
    Route::post('/maintenance', [MaintenanceRequestController::class, 'store'])->name('maintenance.store');
    Route::post('/renter/payments/{payment}/submissions', [PaymentSubmissionController::class, 'store'])->middleware('throttle:10,1')->name('renter.payments.submissions.store');
    Route::post('/bookings/{booking}/review', [ReviewController::class, 'store'])->name('reviews.store');
    Route::patch('/renter/bookings/{booking}/cancel', [BookingLifecycleController::class, 'cancel'])->name('renter.bookings.cancel');
    Route::patch('/renter/bookings/{booking}/terminate', [BookingLifecycleController::class, 'terminate'])->name('renter.bookings.terminate');
});

Route::middleware(['auth', 'contact.verified'])->group(function () {
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/receipts/{transaction}', [PaymentReceiptController::class, 'show'])->name('payments.receipts.show');
    Route::get('/payment-submissions/{submission}/evidence', [PaymentSubmissionController::class, 'evidence'])->name('payments.submissions.evidence');
    Route::get('/maintenance/{maintenanceRequest}/evidence', [MaintenanceRequestController::class, 'evidence'])->name('maintenance.evidence');
    Route::get('/maintenance', [MaintenanceRequestController::class, 'index'])->name('maintenance.index');
    Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
});
