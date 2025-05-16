# Sustainability e-Commerce Project

## Overview
A simple web application that connects consumers with discounted products nearing their expiration date. Markets can list these products at a lower price, preventing waste, while consumers can purchase them at a discount.

## Features
- Two user types: Markets and Consumers
- Markets can list products approaching expiration dates with discounted prices
- Consumers can search for products, add them to cart, and purchase
- Email verification for user registration
- Simple and functional design

## Setup Instructions
1. Place the files in your web server directory
2. Import the database schema from `database/schema.sql`
3. Update database credentials in `config/config.php`
4. Ensure your server supports PHP mail() function
5. Visit the website to start using the application

## Project Structure
- `/config` - Configuration files
- `/includes` - Helper functions for database, security, etc.
- `/assets` - Static assets like images and styles
- `/market` - Market user area
- `/templates` - Header and footer templates 