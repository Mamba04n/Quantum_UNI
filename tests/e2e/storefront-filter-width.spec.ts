import {test,expect} from '@playwright/test';
test.use({video:'off'});
test('filters and grid use wide viewport including zoom-out equivalent',async({page},info)=>{
 for(const width of [1366,1920,3840,5760]){
 await page.setViewportSize({width,height:1100});await page.goto('/productos');
 const layout=await page.locator('.products-layout').boundingBox();const sidebar=await page.locator('.products-sidebar').boundingBox();
 expect(layout!.width).toBeGreaterThan(width*.88);expect(sidebar!.width).toBeGreaterThanOrEqual(260);
 const cols=await page.locator('.products-cards-grid').evaluate(el=>getComputedStyle(el).gridTemplateColumns.split(' ').length);expect(cols).toBeGreaterThanOrEqual(width>=3840?8:3);
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
 await page.screenshot({path:info.outputPath(`filters-${width}.png`)});
 }
});
