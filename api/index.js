
const { Telegraf, Markup } = require('telegraf');

// Inisialisasi bot
const bot = new Telegraf(process.env.BOT_TOKEN);

// ==================== COMMAND HANDLERS ====================

// START COMMAND dengan keyboard menu
bot.start(async (ctx) => {
  console.log(`User ${ctx.from.id} started bot`);
  
  const welcomeMessage = `👋 Halo *${ctx.from.first_name}*!\n\n` +
    `*🤖 BOT MULTI-FUNGSI*\n` +
    `Bot ini berjalan di *Vercel Serverless*\n\n` +
    `Silakan pilih menu di bawah:`;
  
  await ctx.replyWithMarkdown(welcomeMessage, 
    Markup.keyboard([
      ['🩺 Cek Kesehatan', '🖼️ Foto Profil'],
      ['⏰ Waktu', '📊 Status Server'],
      ['ℹ️ Tentang Bot', '🔧 Bantuan']
    ]).resize()
  );
  
  // Kirim juga command list
  await ctx.replyWithMarkdown(
    '*📋 PERINTAH YANG TERSEDIA:*\n\n' +
    '*/start* - Memulai bot & menu\n' +
    '*/health* - Cek kesehatan sistem\n' +
    '*/profile* - Lihat foto profil\n' +
    '*/time* - Waktu sekarang\n' +
    '*/status* - Status server Vercel\n' +
    '*/about* - Tentang bot ini\n' +
    '*/help* - Bantuan\n' +
    '*/menu* - Tampilkan menu keyboard'
  );
});

// HELP COMMAND
bot.help((ctx) => {
  ctx.replyWithMarkdown(
    '*🆘 BANTUAN:*\n\n' +
    'Gunakan keyboard atau ketik command:\n' +
    '• Klik button di keyboard\n' +
    '• Atau ketik command dimulai dengan /\n\n' +
    '*📞 Support:*\n' +
    'Jika ada masalah, laporkan ke developer.'
  );
});

// ==================== FEATURE FUNCTIONS ====================

// 1. CEK KESEHATAN SISTEM
bot.hears('🩺 Cek Kesehatan', async (ctx) => {
  try {
    const startTime = Date.now();
    
    // Simulasi beberapa cek
    const checks = {
      bot_api: '✅ Online',
      vercel_server: '✅ Responsif',
      database: '⏳ Simulasi OK',
      memory_usage: `${(process.memoryUsage().heapUsed / 1024 / 1024).toFixed(2)} MB`
    };
    
    const latency = Date.now() - startTime;
    
    await ctx.replyWithMarkdown(
      `*🩺 LAPORAN KESEHATAN SISTEM:*\n\n` +
      `• Bot API: ${checks.bot_api}\n` +
      `• Server: ${checks.vercel_server}\n` +
      `• Database: ${checks.database}\n` +
      `• Memory: ${checks.memory_usage}\n` +
      `• Latency: ${latency}ms\n\n` +
      `*📊 STATUS:* SEMUA SISTEM BERJALAN NORMAL ✅`
    );
    
  } catch (error) {
    ctx.reply('❌ Gagal melakukan health check');
  }
});

bot.command('health', async (ctx) => {
  await ctx.replyWithMarkdown(
    '*🏥 HEALTH CHECK:*\n\n' +
    '• Server: Vercel Serverless ✅\n' +
    '• Runtime: Node.js ✅\n' +
    '• Uptime: 100% (simulasi)\n' +
    '• Response: < 100ms ✅\n\n' +
    'Semua sistem berfungsi normal! 🟢'
  );
});

// 2. FOTO PROFIL USER
bot.hears('🖼️ Foto Profil', async (ctx) => {
  try {
    await ctx.reply('🔄 Mengambil foto profil...');
    
    // Cek jika ada foto profil
    const profilePhotos = await ctx.telegram.getUserProfilePhotos(ctx.from.id);
    
    if (profilePhotos.total_count > 0) {
      const fileId = profilePhotos.photos[0][0].file_id;
      await ctx.replyWithPhoto(fileId, {
        caption: `📸 Foto profil *${ctx.from.first_name}*\n` +
                 `ID: ${ctx.from.id}\n` +
                 `Username: @${ctx.from.username || 'tidak ada'}`,
        parse_mode: 'Markdown'
      });
    } else {
      await ctx.reply('❌ Anda tidak memiliki foto profil di Telegram.');
    }
    
  } catch (error) {
    console.error('Profile error:', error);
    await ctx.reply('⚠️ Tidak bisa mengambil foto profil. Pastikan foto profil Anda publik.');
  }
});

bot.command('profile', async (ctx) => {
  await ctx.replyWithMarkdown(
    `*👤 INFORMASI PROFIL:*\n\n` +
    `• Nama: ${ctx.from.first_name} ${ctx.from.last_name || ''}\n` +
    `• ID: ${ctx.from.id}\n` +
    `• Username: @${ctx.from.username || 'tidak ada'}\n` +
    `• Bahasa: ${ctx.from.language_code || 'tidak diketahui'}\n\n` +
    `Untuk foto profil, gunakan menu "🖼️ Foto Profil"`
  );
});

// 3. WAKTU SEKARANG
bot.hears('⏰ Waktu', async (ctx) => {
  const now = new Date();
  const jakartaTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
  
  await ctx.replyWithMarkdown(
    `*🕐 WAKTU SEKARANG:*\n\n` +
    `• UTC: ${now.toISOString()}\n` +
    `• Jakarta: ${jakartaTime.toLocaleString('id-ID')}\n` +
    `• Unix Timestamp: ${Math.floor(Date.now() / 1000)}\n\n` +
    `📅 Tanggal: ${now.toLocaleDateString('id-ID', { 
      weekday: 'long', 
      year: 'numeric', 
      month: 'long', 
      day: 'numeric' 
    })}`
  );
});

bot.command('time', async (ctx) => {
  const now = new Date();
  await ctx.reply(
    `⏰ *${now.toLocaleTimeString('id-ID')}*\n` +
    `📅 ${now.toLocaleDateString('id-ID')}`,
    { parse_mode: 'Markdown' }
  );
});

// 4. STATUS SERVER
bot.hears('📊 Status Server', async (ctx) => {
  const memory = process.memoryUsage();
  const uptime = process.uptime();
  
  await ctx.replyWithMarkdown(
    `*🖥️ STATUS SERVER VERCEL:*\n\n` +
    `• Platform: Vercel Serverless\n` +
    `• Runtime: Node.js ${process.version}\n` +
    `• Uptime: ${Math.floor(uptime)} detik\n` +
    `• Memory Usage:\n` +
    `  - RSS: ${(memory.rss / 1024 / 1024).toFixed(2)} MB\n` +
    `  - Heap: ${(memory.heapUsed / 1024 / 1024).toFixed(2)} MB / ${(memory.heapTotal / 1024 / 1024).toFixed(2)} MB\n` +
    `• Environment: ${process.env.NODE_ENV || 'production'}\n\n` +
    `*🌐 KONEKSI:*\n` +
    `• Region: sin1 (Singapore)\n` +
    `• Status: Operational ✅`
  );
});

bot.command('status', async (ctx) => {
  await ctx.replyWithMarkdown(
    '*📡 STATUS:*\n\n' +
    '• Bot: Online 🟢\n' +
    '• Server: Vercel 🟢\n' +
    '• API: Telegram 🟢\n' +
    '• Response: Normal\n\n' +
    'Semua sistem berjalan lancar! 🚀'
  );
});

// 5. TENTANG BOT
bot.hears('ℹ️ Tentang Bot', async (ctx) => {
  await ctx.replyWithMarkdown(
    `*🤖 TENTANG BOT INI:*\n\n` +
    `• Nama: Vercel Telegram Bot\n` +
    `• Versi: 2.0.0\n` +
    `• Platform: Vercel Serverless\n` +
    `• Framework: Telegraf.js\n` +
    `• Features:\n` +
    `  ✅ Multi-menu\n` +
    `  ✅ Health check\n` +
    `  ✅ Profile photo\n` +
    `  ✅ Server status\n` +
    `  ✅ Time display\n\n` +
    `*⚙️ TEKNIKAL:*\n` +
    `• Host: Vercel Functions\n` +
    `• Runtime: Node.js 18\n` +
    `• Region: Singapore\n\n` +
    `Dibuat dengan ❤️ untuk demo Vercel`
  );
});

bot.command('about', (ctx) => {
  ctx.replyWithMarkdown(
    '*📝 ABOUT:*\n\n' +
    'Bot Telegram yang dihosting di Vercel Serverless Functions.\n\n' +
    'Fitur lengkap dengan menu interaktif dan berbagai utility tools untuk testing dan demo deployment.'
  );
});

// 6. TAMPILKAN MENU
bot.command('menu', async (ctx) => {
  await ctx.reply(
    'Pilih menu di bawah:',
    Markup.keyboard([
      ['🩺 Cek Kesehatan', '🖼️ Foto Profil'],
      ['⏰ Waktu', '📊 Status Server'],
      ['ℹ️ Tentang Bot', '🔧 Bantuan']
    ]).resize()
  );
});

// 7. ECHO MESSAGE (fallback)
bot.on('text', async (ctx) => {
  const text = ctx.message.text;
  
  // Skip jika sudah dihandle oleh menu
  if (text.startsWith('/') || [
    '🩺 Cek Kesehatan', '🖼️ Foto Profil', 
    '⏰ Waktu', '📊 Status Server',
    'ℹ️ Tentang Bot', '🔧 Bantuan'
  ].includes(text)) {
    return;
  }
  
  // Random response untuk chat biasa
  const responses = [
    `Anda berkata: "${text}"`,
    `Pesan diterima: "${text}"`,
    `📝: "${text}"`,
    `💬: "${text}"`
  ];
  
  const randomResponse = responses[Math.floor(Math.random() * responses.length)];
  await ctx.reply(randomResponse);
});

// 8. HANDLE PHOTO (bonus feature)
bot.on('photo', async (ctx) => {
  const photo = ctx.message.photo.pop();
  await ctx.replyWithMarkdown(
    `*📷 FOTO DITERIMA!*\n\n` +
    `• File ID: \`${photo.file_id}\`\n` +
    `• Size: ${photo.file_size ? (photo.file_size / 1024).toFixed(2) + ' KB' : 'unknown'}\n` +
    `• Resolusi: ${photo.width}x${photo.height}\n\n` +
    `Foto berhasil diterima oleh bot!`
  );
});

// 9. STICKER HANDLER
bot.on('sticker', async (ctx) => {
  await ctx.reply(`😊 Sticker diterima! Emoji: ${ctx.message.sticker.emoji || 'tidak ada'}`);
});

// ==================== VERCEL HANDLER ====================

module.exports = async (req, res) => {
  console.log(`${new Date().toISOString()} - ${req.method} ${req.url}`);
  
  try {
    // Handle preflight CORS
    if (req.method === 'OPTIONS') {
      res.setHeader('Access-Control-Allow-Origin', '*');
      res.setHeader('Access-Control-Allow-Methods', 'POST, GET, OPTIONS');
      res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
      return res.status(200).end();
    }
    
    // Health check endpoint
    if (req.method === 'GET' && req.url === '/health') {
      return res.status(200).json({
        status: 'healthy',
        service: 'Telegram Bot API',
        timestamp: new Date().toISOString(),
        version: '2.0.0'
      });
    }
    
    // Root endpoint info
    if (req.method === 'GET' && req.url === '/') {
      return res.status(200).json({
        message: '🤖 Telegram Bot is running on Vercel',
        endpoints: {
          webhook: 'POST /',
          health: 'GET /health',
          status: 'GET /'
        },
        features: [
          'Health Check',
          'Profile Photo',
          'Server Status',
          'Time Display',
          'Interactive Menu'
        ]
      });
    }
    
    // Handle Telegram webhook (POST requests)
    if (req.method === 'POST') {
      await bot.handleUpdate(req.body, res);
    } else {
      res.status(404).json({ error: 'Not found' });
    }
    
  } catch (error) {
    console.error('❌ Server error:', error);
    res.status(500).json({ 
      error: 'Internal server error',
      message: error.message 
    });
  }
};
