export default async function run(page) {
  await page.locator('#email_or_phone').fill("' OR 1=1 -- ")
  await page.locator('#password').fill('not-a-real-password')
  await page.getByRole('button', { name: 'Login' }).click()
  await page.waitForLoadState('networkidle')
  const alert = await page.locator('.alert-danger').textContent().catch(() => '')
  return {
    title: await page.title(),
    result: alert?.trim() || 'No error message displayed',
    remainsOnLogin: page.url().endsWith('/login.php')
  }
}
