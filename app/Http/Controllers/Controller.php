<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    description: "JKDM CRM API - Customer Relationship Management System for Malaysian Government Agencies",
    title: "JKDM CRM API Documentation",
    contact: new OA\Contact(
        email: "support@jkdm.gov.my"
    )
)]
#[OA\Server(
    url: "http://localhost:8000",
    description: "Local Development Server"
)]
#[OA\Server(
    url: "https://api.jkdm.gov.my",
    description: "Production Server"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    name: "Authorization",
    in: "header",
    bearerFormat: "JWT",
    scheme: "bearer"
)]
#[OA\Tag(
    name: "Authentication",
    description: "User authentication and authorization endpoints"
)]
#[OA\Tag(
    name: "Agents",
    description: "Agent management and metrics"
)]
#[OA\Tag(
    name: "Customers",
    description: "Customer management and 360-degree profile"
)]
#[OA\Tag(
    name: "Tickets",
    description: "Ticket management and tracking"
)]
#[OA\Tag(
    name: "Menus",
    description: "Role-based menu management"
)]
#[OA\Tag(
    name: "Mitel",
    description: "Mitel telephony integration"
)]
abstract class Controller
{
    //
}
