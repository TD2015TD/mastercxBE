# CRM API - JKDM Customer Relationship Management System

Laravel 11.x backend API for JKDM CRM system with JWT authentication.

## Features

- **Ticket Management**: Multi-department ticket system with SLA tracking
- **Customer 360**: Complete customer interaction history
- **Category Management**: Hierarchical ticket classification
- **Escalation Workflow**: Multi-level escalation (L1→L2→Supervisor→Manager)
- **SLA Configuration**: Database-driven SLA rules per department/priority
- **Agent Dashboard**: Real-time metrics and queue management
- **Supervisor Dashboard**: Team performance and analytics
- **Reporting API**: Metrics endpoints for Superset/BI integration
- **Email Integration**: IMAP-based email-to-ticket automation (planned)

## Technology Stack

- **Framework**: Laravel 11.x
- **Database**: PostgreSQL
- **Authentication**: JWT (tymon/jwt-auth)
- **API**: RESTful with versioning (/api/v1)
- **Reporting**: Apache Superset integration

## Installation

```bash
# Clone repository
git clone https://github.com/syafiqapextreasure/crm-api.git
cd crm-api

# Install dependencies
composer install

# Configure environment
cp .env.example .env
php artisan key:generate
php artisan jwt:secret

# Run migrations
php artisan migrate

# Start development server
php artisan serve
```

## API Documentation

See documentation files:
- [Agent API](AGENT_API.md)
- [Customer API](CUSTOMER_API.md)
- [Ticket API](TICKET_API.md)
- [Language API](LANGUAGE_API.md)
- [Phase 1 Implementation Summary](PHASE1_IMPLEMENTATION_SUMMARY.md)

## License

Proprietary - JKDM Project
