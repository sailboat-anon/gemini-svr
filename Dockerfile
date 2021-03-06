FROM php:8.0.2-cli-alpine
RUN apk add git
RUN git clone git://github.com/sailboat-anon/gemini-svr.git
COPY certs/sailboat-anon.space/combined.pem /gemini-svr/certs/sailboat-anon.space/combined.pem
COPY hosts/ /gemini-svr/hosts/
EXPOSE 1965
RUN ["php", "-f", "/gemini-svr/gemini-svr.php", "password"]