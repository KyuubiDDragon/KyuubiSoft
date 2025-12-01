FROM nginx:alpine

# Remove default config
RUN rm /etc/nginx/conf.d/default.conf

# Copy custom nginx config
COPY nginx.conf /etc/nginx/nginx.conf
COPY conf.d/default.conf /etc/nginx/conf.d/default.conf

# Create directories for frontend and backend files
RUN mkdir -p /var/www/frontend /var/www/backend/public

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
