
// DEBUG MODE - Simple bot
console.log('=== BOT LOADING ===');

// Cek environment variable
if (!process.env.BOT_TOKEN) {
  console.error('❌ ERROR: BOT_TOKEN is missing!');
} else {
  console.log('✅ BOT_TOKEN found (first 10 chars):', process.env.BOT_TOKEN.substring(0, 10) + '...');
}

// Import telegraf dengan error handling
let Telegraf;
try {
  Telegraf = require('telegraf').Telegraf;
  console.log('✅ Telegraf module loaded');
} catch (error) {
  console.error('❌ Failed to load telegraf:', error.message);
}

// Buat bot instance jika token ada
const bot = process.env.BOT_TOKEN && Telegraf ? new Telegraf(process.env.BOT_TOKEN) : null;

if (bot) {
  // Simple commands
  bot.start((ctx) => {
    console.log('Start command from:', ctx.from.id);
    return ctx.reply('🎉 BOT AKTIF DI VERCEL!\nKetik apapun...');
  });

  bot.on('text', (ctx) => {
    return ctx.reply(`Kamu bilang: "${ctx.message.text}"`);
  });

  console.log('✅ Bot commands registered');
}

// Vercel function handler
module.exports = async (req, res) => {
  console.log(`\n=== REQUEST ${new Date().toISOString()} ===`);
  console.log('Method:', req.method);
  console.log('Path:', req.url);
  
  try {
    // Cek jika bot tidak bisa diinisialisasi
    if (!bot) {
      console.error('Bot not initialized - check BOT_TOKEN');
      return res.status(500).json({ 
        error: 'Bot initialization failed',
        hint: 'Check BOT_TOKEN environment variable'
      });
    }

    // Handle POST (Telegram webhook)
    if (req.method === 'POST') {
      console.log('Processing Telegram update...');
      await bot.handleUpdate(req.body, res);
      console.log('✅ Update processed');
    } 
    // Handle GET (health check)
    else {
      res.status(200).json({
        status: 'online',
        service: 'Telegram Bot',
        platform: 'Vercel',
        timestamp: new Date().toISOString(),
        endpoints: {
          webhook: 'POST /',
          health: 'GET /'
        }
      });
    }
  } catch (error) {
    console.error('❌ CRITICAL ERROR:', error.message);
    console.error(error.stack);
    
    res.status(500).json({
      error: 'Server error',
      message: error.message,
      timestamp: new Date().toISOString()
    });
  }
};
