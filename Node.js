# 1. Initialize project and install dependencies
npm init -y
npm install express axios dotenv

# 2. Create the root files
touch server.js .env

# 3. Create folders and module files
mkdir -p src/routes src/controllers src/services
touch src/routes/cargoRoutes.js
touch src/controllers/cargoController.js
touch src/services/cargoService.js