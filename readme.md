# AWS Spot Pricing App

The **AWS Spot Pricing App** is designed to fetch AWS Spot Instance pricing data using a PHP script and the AWS SDK, store and manage the data in a MySQL database, and provide a PHP backend with a Vue.js frontend for easy access and visualization.

## Quick Start Guide

Follow these steps to quickly set up and run the AWS Spot Pricing App:

1. **Leave the Default Configuration**
   
   - Ensure that the default settings in the `backend/src/config.php` file are intact. If you need to make any changes later, you can update the configuration as required.

2. **Ensure MySQL is Running**
   
   - Make sure you have a running MySQL instance accessible according to `backend/src/config.php` file.


3. **Run the Fetch Spot Data Script**
   
   - Navigate to the `backend/src` directory:
     ```bash
     cd backend/src
     ```
   - Execute the `fetch-spot-data.php` script to fetch and populate the Spot pricing data. The default data range is the last 5 days. **Note:** This process may take some time depending on the volume of data.
     ```bash
     php fetch-spot-data.php
     ```

4. **Install Backend Dependencies and Run the PHP Backend**
   
   - Navigate back to the `backend` directory:
   - Install PHP dependencies using Composer:
     ```bash
     composer install
     ```
   - Start the PHP backend server:
     ```bash
     php -S localhost:8080 -t src/public
     ```
   - The backend API will now be running at `http://localhost:8080`.

5. **Install Frontend Dependencies and Start the Vue.js Frontend**
   
   - Open a new terminal window or tab.
   - Navigate to the `frontend` directory:
   - Install Node.js dependencies using npm:
     ```bash
     npm install
     ```
   - Start the Vue.js development server on port 3000:
     ```bash
     npm run serve -- --port 3000
     ```
   - The frontend application will be accessible at `http://localhost:3000`.

---

**You're all set!** Open your browser and navigate to `http://localhost:3000` to start using the AWS Spot Pricing App.

## Fetching Data Process

The `fetch-spot-data` script performs the following functions:

1. **spot_prices**: Fetches current AWS Spot pricing data and stores it in the `spot_prices` table.
2. **latest_spot_prices**: Calculates and stores the latest Spot price for each instance type and region combination in the `latest_spot_prices` table.
3. **steal_spot_pricing**: Identifies and stores "steals" by finding the five lowest Spot prices per region and per instance type in the `steal_spot_pricing` table.

The `fetch-spot-data` script can be configured in the `backend/src/config.php` file.

## PHP Backend

The PHP backend serves as the API layer, providing RESTful endpoints for data retrieval and management.

### Requirements

- **PHP**: Version 7.4 or higher
- **Composer**: Dependency manager for PHP

### Installation

1. **Navigate to the Backend Directory**:

2. **Install Dependencies with Composer**:
    ```bash
    composer install
    ```

3. **Run the PHP Backend**:
    ```bash
    php -S localhost:8080 -t src/public
    ```

    This command starts the PHP development server, serving the backend API at `http://localhost:8080`.

---


## Vue.js Frontend

The Vue.js frontend provides a user-friendly interface to view and interact with the AWS Spot pricing data.

### Requirements

- **Node.js**: Version 12 or higher
- **npm**: Node package manager

### Installation

1. **Navigate to the Frontend Directory**:

2. **Install Dependencies**:
    ```bash
    npm install
    ```

### Running the Frontend

Start the development server on port 3000:
```bash
npm run serve -- --port 3000
```

## MySQL Database

The application uses a MySQL database to store and manage AWS Spot pricing data. 

- **Requirements**:
  - Ensure you have access to a running MySQL instance.

- **Setup**:
  - **Configure Database Connection**: Edit the `backend/src/config.php` file with your MySQL database credentials.
  - **Automatic Table Creation**: The `fetch-spot-data` script will automatically create the necessary tables (`spot_prices`, `latest_spot_prices`, `steal_spot_pricing`) if they do not exist.

- **Security Notice**:
  - The `config.php` file is uploaded to Git. **Do not** include any sensitive information such as database passwords. Use environment variables or a secure method to manage secrets securely.