# Use an official PHP runtime as the base image
FROM --platform=linux/amd64 php:7.4-apache

# Install Python and pip
RUN apt-get update && apt-get install -y python3-dev python3-pip

#install tmux
RUN apt-get install -y tmux

#install sqlite3
RUN apt-get install -y sqlite3

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

RUN chmod +x startup.sh 

# Run Apache2 in foreground
ENTRYPOINT ["sh", "-c", "./startup.sh && apache2-foreground"]