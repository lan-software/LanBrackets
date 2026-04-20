<?php

test('home page serves the public landing to guests', function () {
    $this->withoutVite();

    // The root route renders a public Landing page for unauthenticated
    // visitors. Auth gating applies to /dashboard, /competitions, etc.
    $this->get(route('home'))
        ->assertSuccessful();
});
