<?php

test('home page requires authentication', function () {
    $this->withoutVite();

    $this->get(route('home'))
        ->assertForbidden();
});
