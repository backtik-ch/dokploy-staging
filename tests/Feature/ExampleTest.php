<?php

test('the dashboard requires authentication', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});
