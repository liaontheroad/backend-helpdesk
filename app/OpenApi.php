<?php

namespace App;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: "Helpdesk API Documentation",
    version: "1.0.0",
    description: "Dokumentasi API untuk aplikasi E-Ticketing Helpdesk berbasis Laravel."
)]
#[OA\Server(
    url: "http://127.0.0.1:8000",
    description: "Local Development Server"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT"
)]
class OpenApi
{
}