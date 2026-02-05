#!/bin/bash

# Copy custom nginx config
cp /home/site/wwwroot/default /etc/nginx/sites-available/default
service nginx reload
