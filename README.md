# Mini Wallet Backend

A Laravel-based mini wallet application with transaction management, user authentication, and email verification features.

## 🚀 Quick Start with Docker Compose

### Prerequisites
- Docker and Docker Compose installed on your system
- Git

### Setup Instructions

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd mini-wallet-backend
   ```

2. **Create environment file**
   ```bash
   cp .env.example .env
   ```

3. **Configure environment variables**
   Edit `.env` file with your database credentials:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=db
   DB_PORT=3306
   DB_DATABASE=miniwallet
   DB_USERNAME=miniwallet_user
   DB_PASSWORD=your_password
   DB_MYSQL_ROOT_PASSWORD=root_password
   ```

4. **Start the application**
   ```bash
   docker-compose up -d
   ```

5. **Run database migrations and seeders**
   ```bash
   docker compose exec app php artisan migrate
    docker compose exec app php artisan db:seed --class=UserSeeder
   ```

6. **Generate application key**
   ```bash
   docker-compose exec app php artisan key:generate
   ```

## 🌐 Service Endpoints

| Service | URL | Description |
|---------|-----|-------------|
| **Application** | http://localhost:8000 | Main Laravel application |
| **phpMyAdmin** | http://localhost:8080 | Database management interface |
| **MailHog Web UI** | http://localhost:8025 | Email testing interface |

## 📋 API Endpoints

### Health Check
- `GET /api/v1/health-check` - Server health status

### Authentication (Guest Routes)
- `POST /api/v1/register` - User registration
- `POST /api/v1/login` - User login
- `POST /api/v1/forgot-password/email/token` - Request password reset token
- `POST /api/v1/forgot-password/verify/token` - Verify password reset token
- `POST /api/v1/forgot-password/reset-password` - Reset password with token

### Email Verification (Authenticated)
- `POST /api/v1/email/send-token` - Send email verification token
- `POST /api/v1/email/verify-token` - Verify email with token

### User Settings (Authenticated)
- `GET /api/v1/user` - Get current user information
- `POST /api/v1/logout` - User logout
- `POST /api/v1/change-password` - Change user password (requires email verification)

### Transactions (Authenticated + Email Verified)
- `GET /api/v1/transactions` - Get user transactions
- `POST /api/v1/transactions` - Create new transaction

## 🛠️ Development Commands

### Run tests
```bash
docker-compose exec app php artisan test
```

### View logs
```bash
docker-compose logs app
docker-compose logs queue-worker
```

### Access application shell
```bash
docker-compose exec app bash
```

### Stop services
```bash
docker-compose stop
```

## 📁 Project Structure

```
├── app/
│   ├── Http/Controllers/Api/V1/     # API Controllers
│   ├── Models/                      # Eloquent Models
│   ├── Services/                    # Business Logic Services
│   ├── Events/                      # Event Classes
│   ├── Listeners/                   # Event Listeners
│   ├── Mail/                        # Email Templates
│   └── Jobs/                        # Queue Jobs
├── database/
│   ├── migrations/                  # Database Migrations
│   ├── seeders/                     # Database Seeders
├── routes/Api/V1/                   # API Routes
└── tests/                          # Test Files
```

## 🔧 Configuration

The application uses the following services:
- **Laravel Octane** with Swoole for high performance
- **MySQL 8.0** for database
- **Redis** for caching and sessions
- **MailHog** for email testing
- **phpMyAdmin** for database management
- **Queue Workers** for background job processing

## 📝 Notes

- All API endpoints require proper authentication except guest routes
- Email verification is required for sensitive operations
- Background jobs are processed by dedicated queue workers