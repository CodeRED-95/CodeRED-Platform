FROM node:18-slim

WORKDIR /usr/src/app

COPY package*.json ./

RUN npm install

COPY . .

# Install playwright browsers
RUN npx playwright install --with-deps chromium

CMD [ "npm", "start" ]
