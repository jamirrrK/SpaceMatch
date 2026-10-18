<dialog id="spacematch-tutorial" class="tutorial-dialog" aria-label="SpaceMatch tutorial" data-tutorial-dialog>
    <div class="tutorial-frame">
        <header class="tutorial-header">
            <div class="d-flex align-items-center gap-3">
                <span class="brand-mark" aria-hidden="true">S</span>
                <div>
                    <div class="tutorial-eyebrow">SpaceMatch guide</div>
                    <div class="tutorial-duration">Guided tour - about 2 minutes</div>
                </div>
            </div>
            <button class="tutorial-close" type="button" aria-label="Close tutorial" data-tutorial-close>x</button>
        </header>

        <section class="tutorial-welcome" data-tutorial-welcome>
            <div class="tutorial-kicker">A better way to find your space</div>
            <h2 id="tutorial-welcome-title">Welcome to SpaceMatch!</h2>
            <p class="tutorial-lead" id="tutorial-welcome-description">Find rental spaces, compare property details, and manage your SpaceMatch account in one place.</p>
            <p class="tutorial-copy">Take a quick guided tour to discover how SpaceMatch works and learn how to make the most of our platform.</p>

            <div class="tutorial-media-note" role="status">
                <span class="tutorial-media-icon" aria-hidden="true">i</span>
                <p><strong>The narrated video is not available yet.</strong> Start the text tour for a guide to features that are live today.</p>
            </div>

            <div class="tutorial-actions">
                <button class="btn btn-primary-custom" type="button" data-tutorial-start>Watch Tutorial</button>
                <button class="btn btn-outline-dark" type="button" data-tutorial-skip>Skip for Now</button>
            </div>
            <button class="tutorial-never" type="button" data-tutorial-never>Don't Show Again</button>
        </section>

        <section class="tutorial-tour" data-tutorial-tour hidden aria-labelledby="tutorial-tour-title">
            <div class="tutorial-tour-heading">
                <div>
                    <div class="tutorial-kicker">Your guided tour</div>
                    <h2 id="tutorial-tour-title" tabindex="-1">Explore SpaceMatch</h2>
                </div>
                <span class="tutorial-step-count" data-tutorial-progress aria-live="polite"></span>
            </div>
            <nav class="tutorial-chapters" aria-label="Tutorial chapters" data-tutorial-chapters>
                <button type="button" class="tutorial-chapter is-active" data-tutorial-select="0" aria-current="step"><span>01</span>Find a space</button>
                <button type="button" class="tutorial-chapter" data-tutorial-select="1"><span>02</span>Compare details</button>
                <button type="button" class="tutorial-chapter" data-tutorial-select="2"><span>03</span>Your account</button>
                @if(auth()->user()?->hasRole('owner'))
                    <button type="button" class="tutorial-chapter" data-tutorial-select="3"><span>04</span>Manage a listing</button>
                @elseif(auth()->user()?->isAdministrator())
                    <button type="button" class="tutorial-chapter" data-tutorial-select="3"><span>04</span>Review listings</button>
                @endif
            </nav>

            <div class="tutorial-chapter-content" data-tutorial-content>
                <article class="tutorial-chapter-panel" data-tutorial-panel>
                    <span class="tutorial-chapter-index">01 / DISCOVER</span>
                    <h3>Find a space that fits</h3>
                    <p>Browse published, available spaces. Search by property name or location, then narrow results by monthly price, property type, capacity, and amenities.</p>
                    <a class="tutorial-text-link" href="{{ route('properties.index') }}">Browse available spaces <span aria-hidden="true">&rarr;</span></a>
                </article>
                <article class="tutorial-chapter-panel" data-tutorial-panel hidden>
                    <span class="tutorial-chapter-index">02 / DETAILS</span>
                    <h3>Compare the details</h3>
                    <p>Open a listing to see its photos, location, available spaces, monthly prices, capacity, and listed amenities before deciding what to explore next.</p>
                    <a class="tutorial-text-link" href="{{ route('properties.index') }}">Explore listings <span aria-hidden="true">&rarr;</span></a>
                </article>
                <article class="tutorial-chapter-panel" data-tutorial-panel hidden>
                    <span class="tutorial-chapter-index">03 / YOUR ACCOUNT</span>
                    @auth
                        <h3>Keep your account up to date</h3>
                        <p>Your profile is where you can update your account details and password. Your dashboard is available from the navigation at any time.</p>
                        <a class="tutorial-text-link" href="{{ route('profile') }}">Open your profile <span aria-hidden="true">&rarr;</span></a>
                    @else
                        <h3>Choose how you use SpaceMatch</h3>
                        <p>Create an owner or renter account, then sign in to reach the dashboard for your role. Administrator accounts are managed separately.</p>
                        <a class="tutorial-text-link" href="{{ route('register') }}">Create an account <span aria-hidden="true">&rarr;</span></a>
                    @endauth
                </article>
                @if(auth()->user()?->hasRole('owner'))
                    <article class="tutorial-chapter-panel" data-tutorial-panel hidden>
                        <span class="tutorial-chapter-index">04 / OWNER WORKSPACE</span>
                        <h3>Manage a property listing</h3>
                        <p>Create a property, add its rental spaces and amenities, upload photos, and publish when the listing is ready. You can manage only properties owned by your account.</p>
                        <a class="tutorial-text-link" href="{{ route('owner.dashboard') }}">Open the owner dashboard <span aria-hidden="true">&rarr;</span></a>
                    </article>
                @elseif(auth()->user()?->isAdministrator())
                    <article class="tutorial-chapter-panel" data-tutorial-panel hidden>
                        <span class="tutorial-chapter-index">04 / ADMINISTRATION</span>
                        <h3>Review property listings</h3>
                        <p>Use the administrator workspace to search properties, review listing details, and record a moderation status change with a reason.</p>
                        <a class="tutorial-text-link" href="{{ route('admin.properties.index') }}">Open property oversight <span aria-hidden="true">&rarr;</span></a>
                    </article>
                @endif
            </div>

            <footer class="tutorial-tour-footer">
                <button class="btn btn-outline-dark" type="button" data-tutorial-previous>Previous</button>
                <div class="tutorial-tour-actions">
                    <button class="btn btn-primary-custom" type="button" data-tutorial-next>Next chapter</button>
                    <button class="btn btn-primary-custom" type="button" data-tutorial-finish hidden>Finish tour</button>
                    <button class="tutorial-skip-link" type="button" data-tutorial-skip>Skip tutorial</button>
                    <button class="tutorial-never" type="button" data-tutorial-never>Don't Show Again</button>
                </div>
            </footer>
        </section>
    </div>
</dialog>