FROM nginx:alpine

RUN sed -i 's/listen 80;/listen 8080;/' /etc/nginx/conf.d/default.conf

COPY docker-test.html /usr/share/nginx/html/index.html

EXPOSE 8080

CMD ["nginx", "-g", "daemon off;"]
