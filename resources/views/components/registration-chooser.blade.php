<dialog class="registration-chooser" id="registration-chooser" aria-labelledby="registration-choice-title">
    <div class="d-flex justify-content-between gap-3"><div><div class="eyebrow mb-2">Your next chapter</div><h2 class="section-title" id="registration-choice-title">How will you use SpaceMatch?</h2></div><button class="btn-close" type="button" data-registration-close aria-label="Close registration choices"></button></div>
    <p>Choose an account that fits. Each option uses the same secure contact verification.</p>
    <div class="registration-options">
        <a href="{{ route('renter.register') }}"><x-icon name="search" /><strong>I'm looking for a space</strong><span>Register as a Renter</span></a>
        <a href="{{ route('owner.register') }}"><x-icon name="building" /><strong>I want to list my space</strong><span>Register as a Property Owner</span></a>
        <a href="{{ route('student.register') }}"><x-icon name="home" /><strong>I'm looking for student housing</strong><span>Register as a Student Renter</span></a>
    </div>
</dialog>
