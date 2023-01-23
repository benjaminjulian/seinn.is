# Use an official PHP runtime as the base image
FROM php:7.4-apache

# Install Python and pip
RUN apt-get update && apt-get install -y python3-dev python3-pip

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy PHP application files to the container
COPY . /var/www/html/

# Install Python dependencies
COPY requirements.txt /tmp/
RUN pip3 install --no-cache-dir -r /tmp/requirements.txt

# Set the working directory
WORKDIR /var/www/html

# Expose port 80
EXPOSE 80

# Run Apache2 in foreground
ENTRYPOINT ["sh", "-c", "python3 gtfs.py && apache2-foreground"]
