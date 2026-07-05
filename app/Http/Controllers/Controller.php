<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "eShop API Documentation",
    description: "API endpoints for managing and retrieving products, variants, and other eShop resources."
)]
#[OA\Server(
    url: "http://localhost",
    description: "Local development server"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    name: "Authorization",
    in: "header",
    scheme: "bearer",
    bearerFormat: "JWT"
)]
abstract class Controller
{
    //
}
