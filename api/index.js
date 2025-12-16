
// Versi SIMPLE dan PASTI WORKING
const { Telegraf } = require('telegraf');

// Inisialisasi bot
const bot = new Telegraf(process.env.BOT_TOKEN);

// Basic commands
bot.start((ctx) => {
  console.log('Start command received');
  return ctx.reply('✅ Bot aktif dari Vercel!');
});

bot.help((ctx) => {
  return ctx.reply('Gunakan /start untuk memulai');
});

bot.on('text', (ctx) => {
  console.log('Text received:', ctx.message.text);
  return ctx.reply(`Kamu tulis: ${ctx.message.text}`);
});

// Handler untuk Vercel
module.exports = async (req, res) => {
  console.log('Request received:', req.method, req.url);
  
  try {
    // Log untuk debugging
    console.log('BOT_TOKEN exists:', !!process.env.BOT_TOKEN);
    
    if (req.method === 'POST') {
      console.log('Processing Telegram update...');
      
      // Handle update dari Telegram
      await bot.handleUpdate(req.body, res);
      
      console.log('Update processed successfully');
    } else {
      // Untuk GET request
      res.status(200).json({
        success: true,
        message: 'Bot is running on Vercel',
        timestamp: new Date().toISOString(),
        endpoint: '/api (POST) for Telegram webhook'
      });
    }
  } catch (error) {
    console.error('❌ ERROR:', error.message);
    console.error('Stack:', error.stack);
    
    res.status(500).json({
      success: false,
      error: error.message,
      stack: process.env.NODE_ENV === 'development' ? error.stack : undefined
    });
  }
};
