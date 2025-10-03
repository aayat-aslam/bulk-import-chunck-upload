#!/bin/bash

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to display help
show_help() {
    echo -e "${YELLOW}Usage: ./docker-commands.sh [command]${NC}"
    echo -e "\nAvailable commands:"
    echo -e "  ${GREEN}up${NC}          - Start all containers"
    echo -e "  ${GREEN}down${NC}        - Stop and remove all containers"
    echo -e "  ${GREEN}build${NC}       - Build or rebuild services"
    echo -e "  ${GREEN}restart${NC}     - Restart all containers"
    echo -e "  ${GREEN}logs${NC}        - View container logs"
    echo -e "  ${GREEN}artisan${NC}     - Run an Artisan command"
    echo -e "  ${GREEN}composer${NC}    - Run a Composer command"
    echo -e "  ${GREEN}node${NC}        - Run a Node.js command"
    echo -e "  ${GREEN}npm${NC}         - Run an NPM command"
    echo -e "  ${GREEN}db${NC}          - Connect to the database"
    echo -e "  ${GREEN}redis${NC}       - Connect to Redis"
    echo -e "  ${GREEN}bash${NC}        - Open bash in the app container"
    echo -e "  ${GREEN}help${NC}        - Show this help message"
}

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo -e "${RED}Docker is not running. Please start Docker and try again.${NC}" >&2
    exit 1
fi

# Commands
case "$1" in
    up)
        # Copy .env.docker to .env if it doesn't exist
        if [ ! -f .env ]; then
            cp .env.docker .env
            echo -e "${GREEN}Created .env file from .env.docker${NC}"
        fi
        
        # Start all containers
        docker-compose up -d
        
        # Install PHP dependencies
        docker-compose exec app composer install --optimize-autoloader --no-dev
        
        # Generate application key if not exists
        if ! grep -q '^APP_KEY=base64:' .env; then
            docker-compose exec app php artisan key:generate
        fi
        
        # Run migrations and seeders
        docker-compose exec app php artisan migrate --seed
        
        # Install Node.js dependencies
        docker-compose exec node npm install
        
        # Build assets
        docker-compose exec node npm run build
        
        # Set storage permissions
        docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
        docker-compose exec app chmod -R 775 storage bootstrap/cache
        
        # Create storage link
        docker-compose exec app php artisan storage:link
        
        echo -e "\n${GREEN}Application is now running at http://localhost:8000${NC}"
        echo -e "${YELLOW}MailHog interface is available at http://localhost:8025${NC}"
        ;;
        
    down)
        docker-compose down -v
        ;;
        
    build)
        docker-compose build --no-cache
        ;;
        
    restart)
        docker-compose restart
        ;;
        
    logs)
        docker-compose logs -f
        ;;
        
    artisan)
        shift
        docker-compose exec app php artisan "$@"
        ;;
        
    composer)
        shift
        docker-compose exec app composer "$@"
        ;;
        
    node)
        shift
        docker-compose exec node node "$@"
        ;;
        
    npm)
        shift
        docker-compose exec node npm "$@"
        ;;
        
    db)
        docker-compose exec db mysql -ularavel -psecret laravel_assessment
        ;;
        
    redis)
        docker-compose exec redis redis-cli
        ;;
        
    bash)
        docker-compose exec app bash
        ;;
        
    help|*)
        show_help
        ;;
esac
