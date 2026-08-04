#!/bin/bash
# Airsell Cargo Logistics Software Setup Script

echo "🚀 Starting Airsell Cargo setup..."

# 1. Update system
sudo apt update && sudo apt upgrade -y

# 2. Install dependencies
sudo apt install -y apache2 php php-cli php-mysql composer mysql-server git unzip curl

# 3. Clone repository
git clone https://github.com/airsellcargo-01/airsellcargo_website.git /var/www/airsellcargo
cd /var/www/airsellcargo

# 4. Install PHP dependencies
composer install

# 5. Configure environment
cp .env.example .env
echo "Update .env with database credentials, API keys, and ONE Record endpoints."

# 6. Setup database
mysql -u root -p <<EOF
CREATE DATABASE airsellcargo;
CREATE USER 'airsell_user'@'localhost' IDENTIFIED BY 'StrongPassword123!';
GRANT ALL PRIVILEGES ON airsellcargo.* TO 'airsell_user'@'localhost';
FLUSH PRIVILEGES;
EOF

php artisan migrate

# 7. Permissions
sudo chown -R www-data:www-data /var/www/airsellcargo
sudo chmod -R 755 /var/www/airsellcargo

# 8. Restart services
sudo systemctl restart apache2

echo "✅ Airsell Cargo setup complete!"
