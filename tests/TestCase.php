<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tes tidak boleh menghubungi layanan luar (mis. Fonnte) — permintaan yang tidak di-fake langsung gagal
        Http::preventStrayRequests();
    }
}
