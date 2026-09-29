FROM node:22-bookworm-slim
RUN apt-get update && apt-get install -y --no-install-recommends chromium ca-certificates fonts-liberation python3 && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY package*.json ./
RUN npm ci --omit=dev
COPY browser.js worker.js parse_xlsx.py ./
RUN mkdir /data && chown node:node /data /app
USER node
ENV TZ=Asia/Jakarta
CMD ["node","worker.js"]
