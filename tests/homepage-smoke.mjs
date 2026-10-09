export default async function run(page) {
  const results = []
  for (const viewport of [{ width: 390, height: 844 }, { width: 1440, height: 900 }]) {
    await page.setViewportSize(viewport)
    await page.reload()
    const state = await page.evaluate(() => ({
      title: document.title,
      heading: document.querySelector('h1')?.textContent.trim(),
      imageLoaded: Boolean(document.querySelector('.hero-photo')?.naturalWidth),
      documentWidth: document.documentElement.scrollWidth,
      viewportWidth: window.innerWidth,
      serviceCount: document.querySelectorAll('.service-tile').length,
      signupLink: Boolean(document.querySelector('a[href="register.php"]'))
    }))
    results.push({ viewport, ...state })
  }
  for (const route of ['login.php', 'register.php']) {
    await page.goto(`http://localhost:8080/liteup_app/public/${route}`)
    results.push(await page.evaluate(() => ({
      route: location.pathname.split('/').pop(),
      title: document.title,
      formPresent: Boolean(document.querySelector('form')),
      csrfPresent: Boolean(document.querySelector('input[name="csrf_token"]')),
      horizontalOverflow: document.documentElement.scrollWidth > window.innerWidth,
      brandStylesLoaded: getComputedStyle(document.body).backgroundColor !== 'rgba(0, 0, 0, 0)'
    })))
  }
  return results
}
