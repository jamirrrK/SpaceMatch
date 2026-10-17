<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MaintenanceRequestController;
use App\Http\Controllers\Owner\DashboardController as OwnerDashboardController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\Renter\DashboardController as RenterDashboardController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SpaceController;
use App\Http\Controllers\SpaceComparisonController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
Route::get('/properties/{property}', [PropertyController::class, 'show'])->name('properties.show');
Route::get('/spaces', [SpaceController::class, 'index'])->name('spaces.index');
Route::get('/spaces/{space}', [SpaceController::class, 'show'])->name('spaces.show');
Route::get('/compare', [SpaceComparisonController::class, 'index'])->name('spaces.compare.index');
Route::post('/compare/{space}', [SpaceComparisonController::class, 'store'])->name('spaces.compare.store');
Route::delete('/compare/{space}', [SpaceComparisonController::class, 'destroy'])->name('spaces.compare.destroy');
Route::get('/property-images/{image}', [\App\Http\Controllers\PropertyImageController::class, 'show'])->name('property-images.show');

Route::middleware(['guest'])->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

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

Route::middleware(['auth', 'role:admin', 'contact.verified'])->group(function () {
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin/users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('admin.users.index');
    Route::get('/admin/properties', [\App\Http\Controllers\Admin\PropertyController::class, 'index'])->name('admin.properties.index');
    Route::get('/admin/properties/{property}', [\App\Http\Controllers\Admin\PropertyController::class, 'show'])->name('admin.properties.show');
    Route::patch('/admin/properties/{property}/status', [\App\Http\Controllers\Admin\PropertyController::class, 'updateStatus'])->name('admin.properties.status');
    Route::get('/admin/bookings', [\App\Http\Controllers\Admin\BookingController::class, 'index'])->name('admin.bookings.index');
    Route::get('/admin/audit-logs', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('admin.audit-logs.index');
});

Route::middleware(['auth', 'role:owner', 'contact.verified'])->group(function () {
    Route::get('/owner/dashboard', [OwnerDashboardController::class, 'index'])->name('owner.dashboard');
    Route::resource('/owner/properties', \App\Http\Controllers\Owner\PropertyController::class)->except('destroy')->names('owner.properties');
    Route::patch('/owner/properties/{property}/status', [\App\Http\Controllers\Owner\PropertyController::class, 'status'])->name('owner.properties.status');
    Route::patch('/owner/properties/{property}/publish', [\App\Http\Controllers\Owner\PropertyController::class, 'publish'])->name('owner.properties.publish');
    Route::get('/owner/properties/{property}/spaces', [\App\Http\Controllers\Owner\SpaceController::class, 'index'])->name('owner.properties.spaces');
    Route::resource('/owner/spaces', \App\Http\Controllers\Owner\SpaceController::class)->except(['show', 'destroy'])->names('owner.spaces');
    Route::patch('/owner/spaces/{space}/publish', [\App\Http\Controllers\Owner\SpaceController::class, 'publish'])->name('owner.spaces.publish');
    Route::post('/owner/properties/{property}/images', [\App\Http\Controllers\Owner\PropertyImageController::class, 'store'])->name('owner.properties.images.store');
    Route::patch('/owner/images/{image}', [\App\Http\Controllers\Owner\PropertyImageController::class, 'update'])->name('owner.images.update');
    Route::delete('/owner/images/{image}', [\App\Http\Controllers\Owner\PropertyImageController::class, 'destroy'])->name('owner.images.destroy');
});

Route::middleware(['auth', 'role:renter', 'contact.verified'])->group(function () {
    Route::get('/renter/dashboard', [RenterDashboardController::class, 'index'])->name('renter.dashboard');
    Route::get('/renter/preferences', [\App\Http\Controllers\Renter\PreferenceController::class, 'edit'])->name('renter.preferences.edit');
    Route::put('/renter/preferences', [\App\Http\Controllers\Renter\PreferenceController::class, 'update'])->name('renter.preferences.update');
    Route::get('/renter/saved-spaces', [\App\Http\Controllers\Renter\SavedSpaceController::class, 'index'])->name('renter.saved-spaces.index');
    Route::post('/renter/saved-spaces/{space}', [\App\Http\Controllers\Renter\SavedSpaceController::class, 'store'])->name('renter.saved-spaces.store');
    Route::delete('/renter/saved-spaces/{space}', [\App\Http\Controllers\Renter\SavedSpaceController::class, 'destroy'])->name('renter.saved-spaces.destroy');
});

Route::middleware(['auth', 'contact.verified'])->group(function () {
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/maintenance', [MaintenanceRequestController::class, 'index'])->name('maintenance.index');
    Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
});
