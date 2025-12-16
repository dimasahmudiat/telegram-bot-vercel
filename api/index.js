const { Telegraf } = require('telegraf');
const express = require('express');

const app = express();
const bot = new Telegraf(process.env.BOT_TOKEN);

// Command sederhana
bot.start((ctx) => {
  ctx.reply('🎉 Bot aktif dari Vercel!\nKetik /help untuk bantuan');
});

bot.help((ctx) => {
  ctx.reply('Commands:\n/start - Memulai bot\n/hello - Sapaan\n/about - Tentang bot');
});

bot.command('hello', (ctx) => {
  ctx.reply('Halo! 👋 Bot ini berjalan di Vercel Serverless');
});

bot.command('about', (ctx) => {
  ctx.reply('🤖 Bot Telegram\n📡 Hosted on Vercel\n⚡ Powered by Node.js');
});

// Handle pesan teks
bot.on('text', (ctx) => {
  const text = ctx.message.text;
  if (text.toLowerCase().includes('hai')) {
    ctx.reply('Hai juga! 😊');
  } else {
    ctx.reply(`Kamu bilang: "${text}"`);
  }
});

// Middleware
app.use(express.json());

// Webhook endpoint
app.post('/api', async (req, res) => {
  try {
    await bot.handleUpdate(req.body, res);
  } catch (err) {
    console.error(err);
    res.status(500).send('Error');
  }
});

// Health check
app.get('/', (req, res) => {
  res.send('🚀 Telegram Bot is running on Vercel!');
});

app.get('/api/health', (req, res) => {
  res.json({ status: 'healthy', timestamp: new Date() });
});

// Export untuk Vercel
module.exports = app;
