FROM node:22-bookworm-slim
ENV PUPPETEER_SKIP_DOWNLOAD=true NODE_ENV=production
RUN apt-get update && apt-get install -y --no-install-recommends chromium ca-certificates fonts-liberation && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY package*.json ./
RUN npm ci --omit=dev
COPY server.js manager.js monitor.js stock-bot.js commands.js ./
RUN mkdir /data && chown node:node /data /app
USER node
EXPOSE 3000
CMD ["node", "server.js"]
