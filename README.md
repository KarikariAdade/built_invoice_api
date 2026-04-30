# Laravel Invoicing REST API

## Project Overview

This project is a RESTful backend API built with laravel for managing:
 - User authentication (JWT)
 - Customers
 - Products / Inventory
 - Invoices

The system allows authentcated users to create invoices for customers while ensuring inventory stock is validated and products cannot be oversold

---
# Features Implemented

## Requested Specifications
 - [x] An invoice should have issue and due dates
 - [x] An invoice should have a customer
 - [x] An invoice can have at least 1 item
 - [x] Each item should have unit price, quantity, amount and description
 - [x] User authentication and authorization
 - [x] Implement item creation and inventory tracking to ensure the user cannot over sell 

# Installation Guide

## Requirements
 - PHP 8.4+
 - Composer
 - MySQL
 - Email Service (SMTP)

## Installation
 - Clone the repository
 - Run `composer install`
 - Copy `.env.example` to `.env` and update the database and email credentials
 - Run `php artisan key:generate`
 - Run `php artisan migrate`
 - Run `php artisan db:seed`
 - Run `php artisan jwt:secret` to create a JWT secret key
 - Run `php artisan serve` to start the server


# Authentication Guide

JWT Authentication is used to authenticate users.

 - Register a new user
 - Login with the registered user credentials
 - Use the JWT token in the Authorization header (`Authorization: Bearer YOUR_JWT_TOKEN`) for all subsequent requests

# API Documentation

A complete Postman Collection is included with this project submission. File Name is `Built Invoice API.postman_collection.json`

## The Postman Collection Includes

 - Authentication Endpoints
 - Customer Endpoints
 - Invoice Endpoints
   - Creating Invoices
   - Oversold Inventory Validation when creating an invoice
   - Invoice Item validation
 - Product Endpoints
