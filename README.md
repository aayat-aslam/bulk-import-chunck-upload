# 📦 Bulk Import and Chunked File Upload System

<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo">
  <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/React-icon.svg/1280px-React-icon.svg.png" width="150" alt="React Logo">
</p>

A comprehensive file management solution built with Laravel (Backend) and React (Frontend), designed to handle large file uploads efficiently through chunking and background processing. This system is perfect for applications that require reliable file uploads, image processing, and data import capabilities.

## ✨ Key Features

### 🚀 Core Functionality
- **Chunked File Uploads**
  - Break large files into smaller chunks for reliable uploads
  - Resume interrupted uploads automatically
  - Progress tracking for better user experience

- **Background Processing**
  - Asynchronous file processing using Laravel Queues
  - Job batching for handling multiple uploads
  - Real-time progress updates via WebSockets (optional)

- **Image Processing**
  - Automatic generation of multiple image variants (thumbnails, optimized versions)
  - Support for different image formats (JPEG, PNG, WebP)
  - Watermarking and image optimization

- **Bulk Data Import**
  - CSV/Excel file import functionality
  - Data validation and error handling
  - Batch processing for large datasets

### 🛠 Technical Features
- **Frontend**
  - Built with React.js and Inertia.js for a SPA-like experience
  - Responsive design with Tailwind CSS
  - Drag-and-drop file upload interface
  - Real-time progress indicators

- **Backend**
  - RESTful API architecture
  - JWT Authentication with Laravel Sanctum
  - Database migrations and seeders
  - Comprehensive error handling and logging

- **Performance**
  - Queue workers for background processing
  - File storage optimization
  - Caching strategies for better performance
  - Database query optimization

### 🔒 Security
- File type validation
- Virus scanning integration
- Rate limiting for API endpoints
- CSRF protection
- Secure file storage with proper permissions

## 🐳 Docker Setup

This project includes Docker configuration for easy development and deployment. The Docker setup includes:

- **PHP-FPM 8.2** for running the Laravel application
- **Nginx** as the web server
- **MySQL 8.0** for the database
- **Redis** for caching and queues
- **Node.js 18** for frontend assets
- **MailHog** for email testing

### Prerequisites

- Docker and Docker Compose installed on your system
- At least 4GB of free RAM
- At least 2 CPU cores

### Getting Started

1. **Clone the repository** (if you haven't already)
   ```bash
   git clone https://github.com/yourusername/your-repo.git
   cd your-repo
   ```

2. **Make the docker-commands script executable**
   ```bash
   chmod +x docker-commands.sh
   ```

3. **Start the application**
   ```bash
   ./docker-commands.sh up
   ```
   This will:
   - Build and start all containers
   - Install PHP dependencies
   - Generate application key if needed
   - Run database migrations and seeders
   - Install Node.js dependencies
   - Build frontend assets
   - Set proper file permissions

4. **Access the application**
   - Web Interface: http://localhost:8000
   - MailHog (Email Testing): http://localhost:8025
   - MySQL: localhost:3306 (user: laravel, password: secret)
   - Redis: localhost:6379

### Available Commands

Use the `docker-commands.sh` script to manage the application:

```bash
# Start the application
./docker-commands.sh up

# Stop all containers
./docker-commands.sh down

# View container logs
./docker-commands.sh logs

# Run Artisan commands
./docker-commands.sh artisan migrate
./docker-commands.sh artisan db:seed

# Run Composer commands
./docker-commands.sh composer require package/name

# Run NPM commands
./docker-commands.sh npm install
./docker-commands.sh npm run dev

# Access database console
./docker-commands.sh db

# Access Redis console
./docker-commands.sh redis

# Open bash in the app container
./docker-commands.sh bash
```

### Environment Variables

Copy `.env.docker` to `.env` and modify as needed:

```bash
cp .env.docker .env
```

Key environment variables to configure:

- `APP_NAME` - Your application name
- `APP_ENV` - Environment (local, staging, production)
- `APP_DEBUG` - Debug mode (true/false)
- `APP_URL` - Application URL
- Database credentials
- Redis configuration
- Mail configuration

### Development Workflow

1. **Frontend Development**
   - Run `./docker-commands.sh npm run dev` for development with hot-reload
   - The frontend will be available at http://localhost:8000

2. **Backend Development**
   - The PHP code is mounted into the container, so changes are reflected immediately
   - Access logs with `./docker-commands.sh logs`

3. **Database Management**
   - Access MySQL: `./docker-commands.sh db`
   - Run migrations: `./docker-commands.sh artisan migrate`

### Production Deployment

For production, make sure to:

1. Update the `.env` file with production values
2. Set `APP_ENV=production`
3. Set `APP_DEBUG=false`
4. Configure proper database credentials
5. Set up proper SSL certificates
6. Configure proper file permissions

### Troubleshooting

- **Port conflicts**: Check if ports 8000, 3306, 6379 are available
- **Container issues**: Run `docker ps -a` to check container status
- **Logs**: Use `./docker-commands.sh logs` to view logs
- **Rebuild**: If you make changes to Docker configuration, run `docker-compose build --no-cache`

### Cleanup

To stop and remove all containers, volumes, and networks:

```bash
docker-compose down -v
```

To remove all unused containers, networks, and images:

```bash
docker system prune -a
```

## 🏗 System Architecture

### Backend Structure
```
app/
├── Console/          # Artisan commands
├── Http/
│   ├── Controllers/ # Request handlers
│   ├── Middleware/  # Custom middleware
│   └── Requests/    # Form requests
├── Jobs/            # Background jobs
├── Models/          # Eloquent models
└── Services/        # Business logic

config/              # Configuration files
database/
├── migrations/      # Database migrations
├── seeders/         # Database seeders

routes/
├── api.php         # API routes
├── web.php         # Web routes
└── channels.php    # Broadcasting channels
```

### Frontend Structure
```
resources/
├── js/
│   ├── Components/  # Reusable React components
│   ├── Layouts/     # Page layouts
│   └── Pages/       # Page components
├── css/             # Stylesheets
└── views/           # Blade templates
```

## 🚀 Getting Started

### Prerequisites

- **PHP 8.1+** with extensions: BCMath, Ctype, cURL, DOM, Fileinfo, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML
- **Composer** (PHP package manager)
- **Node.js 16+** & NPM (for frontend assets)
- **MySQL 5.7+** or **MariaDB 10.3+**
- **Redis** (recommended for queue and caching)

### 🛠 Installation Guide

#### 1. Clone the Repository
```bash
git clone https://github.com/aayat-aslam/bulk-import-chunck-upload.git
cd bulk-import-chunck-upload
```

#### 2. Install Dependencies
```bash
# Install PHP dependencies
composer install --optimize-autoloader --no-dev

# Install Node.js dependencies
npm install
```

#### 3. Environment Configuration
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

#### 4. Database Setup
1. Create a new MySQL database
2. Update `.env` with your database credentials:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=your_database_name
   DB_USERNAME=your_database_user
   DB_PASSWORD=your_database_password
   ```

3. Run migrations and seed the database:
   ```bash
   php artisan migrate --seed
   ```

#### 5. Storage Configuration
```bash
# Create storage link
php artisan storage:link

# Set proper permissions
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
```

#### 6. Queue Configuration (Recommended)
Configure your `.env` to use Redis or database queues:
```env
QUEUE_CONNECTION=redis  # or 'database' if Redis is not available
```

Start the queue worker in a separate terminal:
```bash
php artisan queue:work --tries=3
```

#### 7. Start Development Servers
In separate terminal windows:
```bash
# Terminal 1: Backend server
php artisan serve

# Terminal 2: Frontend assets (Vite)
npm run dev

# Terminal 3: Queue worker (if using queues)
php artisan queue:work
```

#### 8. Access the Application
- Web Interface: [http://localhost:8000](http://localhost:8000)
- API Base URL: [http://localhost:8000/api](http://localhost:8000/api)

Default Admin Credentials:
- Email: admin@example.com
- Password: password

## 🔧 Environment Variables

Key environment variables to configure in `.env`:

```env
APP_NAME="Bulk Import System"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

QUEUE_CONNECTION=sync  # or 'database'/'redis' for production
SESSION_DRIVER=file
CACHE_DRIVER=file

# File Upload Settings
UPLOAD_CHUNK_SIZE=1048576  # 1MB chunks
UPLOAD_MAX_FILE_SIZE=104857600  # 100MB max file size

# Image Processing
IMAGE_DRIVER=gd  # or 'imagick' if installed
IMAGE_QUALITY=80

# Thumbnail Sizes (width x height)
THUMBNAIL_SIZES=256x256,512x512,1024x1024
```

## 🚀 Deployment

### Production Requirements
- PHP 8.1+ with OPcache enabled
- Nginx/Apache web server
- MySQL/MariaDB
- Redis (recommended)
- Supervisor (for queue workers)
- SSL Certificate (Let's Encrypt)

### Deployment Steps
1. Set up your production environment variables
2. Run `composer install --optimize-autoloader --no-dev`
3. Run `npm run build`
4. Set up queue workers using Supervisor
5. Configure your web server (Nginx/Apache)
6. Set up proper file permissions
7. Configure SSL
8. Set up monitoring and logging

## 🛠 Configuration

### Queue Workers
For processing uploads in the background, run:
```bash
php artisan queue:work
```

### Storage Link
To make uploaded files accessible from the web:
```bash
php artisan storage:link
```

### Environment Variables
Key environment variables to configure:
- `APP_ENV`: Set to `local` for development, `production` for production
- `APP_DEBUG`: Set to `true` in development, `false` in production
- `QUEUE_CONNECTION`: Set to `database` or `redis` for queue processing

## 📚 API Documentation

The API endpoints are available at `/api` and are protected by Sanctum authentication.

### Authentication
- `POST /api/register` - Register a new user
- `POST /api/login` - Login
- `POST /api/logout` - Logout (authenticated)

### File Uploads
- `POST /api/upload/chunk` - Upload a file chunk
- `POST /api/upload/complete` - Complete a chunked upload
- `GET /api/uploads` - List all uploads

## 🧪 Running Tests

```bash
php artisan test
```

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is open-sourced under the [MIT license](LICENSE).

## 👨‍💻 Author

- [Aayat Aslam](https://github.com/aayat-aslam)

---

Built with ❤️ using Laravel and React

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
