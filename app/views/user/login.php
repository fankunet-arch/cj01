  <nav class="nav">
    <a class="back" href="/"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>返回</a>
    <a class="brand" href="/"><span class="zh">西华<span class="hl">招聘</span></span></a>
    <span class="spacer"></span>
    <span class="dom">zhaopin.es</span>
  </nav>

  <div class="shell u-loginwrap">
    <div class="u-login">
      <h1>登录 / 注册</h1>
      <p class="sub">用 Google 账号一键登录，<b>无需密码</b>。<br>注册后可以随时<b>编辑、顶帖、下架</b>自己发的信息。</p>
      <?php if ($err !== ''): ?><p class="u-err"><?= zp_e($err) ?></p><?php endif; ?>
      <?php if ($authUrl !== ''): ?>
      <a class="u-google" href="<?= zp_e($authUrl) ?>">
        <svg viewBox="0 0 24 24" width="20" height="20"><path fill="#4285F4" d="M23.5 12.3c0-.9-.1-1.5-.3-2.2H12v4.1h6.5c-.1 1.1-.8 2.7-2.4 3.8l3.8 2.9c2.3-2.1 3.6-5.1 3.6-8.6z"/><path fill="#34A853" d="M12 24c3.2 0 6-1.1 7.9-2.9l-3.8-2.9c-1 .7-2.4 1.2-4.1 1.2-3.2 0-5.8-2.1-6.8-5l-3.9 3C3.3 21.3 7.3 24 12 24z"/><path fill="#FBBC05" d="M5.2 14.4c-.2-.7-.4-1.5-.4-2.4s.1-1.7.4-2.4l-4-3C.4 8.2 0 10 0 12s.4 3.8 1.3 5.4l3.9-3z"/><path fill="#EA4335" d="M12 4.7c1.8 0 3 .8 3.7 1.4l2.7-2.7C16.6 1.3 14 0 12 0 7.3 0 3.3 2.7 1.3 6.6l3.9 3c1-2.9 3.6-4.9 6.8-4.9z"/></svg>
        使用 Google 登录
      </a>
      <?php else: ?>
      <p class="u-err">登录服务尚未配置，请稍后再试。</p>
      <?php endif; ?>
      <?php if ($devName !== ''): ?>
      <a class="u-btn u-devbtn" href="/user/login?dev=1">本地调试直登（<?= zp_e($devName) ?>）</a>
      <p class="u-devnote">⚠ 仅本地调试可见：config.dev.fake_user_name 生产环境必须留空</p>
      <?php endif; ?>
      <p class="u-guest">不想注册？<a href="/publish">游客也能直接发布</a>，只是发出后不能再改。</p>
    </div>
  </div>
