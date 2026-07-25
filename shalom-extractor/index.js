const express = require('express');
const { chromium } = require('playwright-chromium');

const app = express();
const port = 3000;

app.use(express.json());

app.post('/extract', async (req, res) => {
    const { chosenFileContent } = req.body;

    if (!chosenFileContent) {
        return res.status(400).json({ error: 'chosenFileContent is required' });
    }

    try {
        const browser = await chromium.launch();
        const context = await browser.newContext();
        const page = await context.newPage();

        await page.goto('https://shalom.com.pe/agencias/');

        const agencies = await page.evaluate(() => window.Service.sendPost('agencias/listar'));

        await browser.close();
        
        // TODO: Process agencies and merge with chosenFileContent

        res.json({ agencies });
    } catch (error) {
        console.error(error);
        res.status(500).json({ error: 'Failed to extract agencies' });
    }
});

app.listen(port, () => {
    console.log(`Shalom extractor listening at http://localhost:${port}`);
});
