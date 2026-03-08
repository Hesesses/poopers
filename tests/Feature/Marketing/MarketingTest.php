<?php

it('home page loads', function () {
    $this->get('/')->assertOk();
});

it('privacy page loads', function () {
    $this->get('/privacy')->assertOk();
});

it('terms page loads', function () {
    $this->get('/terms')->assertOk();
});
