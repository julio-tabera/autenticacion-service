FROM nginx:1.21

ENV TZ=America/Montevideo

RUN echo "America/Montevideo" > /etc/timezone
RUN dpkg-reconfigure -f noninteractive tzdata