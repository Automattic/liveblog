import puppeteer from 'puppeteer';

const APP_ADMIN = 'http://192.168.0.15:3000/wp-admin';
const APP_ADD_NEW = 'http://192.168.0.15:3000/wp-admin/post-new.php';
const USER = 'bb_admin';
const PASSWORD = 'admin';
const TIMEOUT = 30000;

let browser;
let page;

const renderEntry = async (content = false) => {
  await page.click('.public-DraftEditor-content');
  await page.keyboard.type(content || 'This is some test content');
  await page.click('.liveblog-publish-btn');
};

const renderEntries = async (amount, content = false) => {
  let times = amount;
  if (times === 0) return Promise.resolve([]);
  times -= 1;
  const entries = await renderEntry().then(() => renderEntries(times, content));
  return Promise.resolve(entries);
};

describe('End to End', async () => {
  beforeAll(async () => {
    browser = await puppeteer.launch({ headless: false });
    page = await browser.newPage();
  }, TIMEOUT);

  it('should create a new liveblog', async () => {
    await page.goto(APP_ADMIN);
    await page.evaluate((login, pass) => {
      document.querySelector('#user_login').value = login;
      document.querySelector('#user_pass').value = pass;
      document.querySelector('#wp-submit').click();
    }, USER, PASSWORD);

    await page.waitForNavigation();
    await page.goto(APP_ADD_NEW, { waitUntil: 'load' });
    await page.click('input[name=post_title]');
    await page.keyboard.type('Liveblog Test');
    await page.click('#liveblog button[value=enable]');
    await page.click('#publish');
    await page.waitForNavigation();
    await page.click('#message a');
  }, TIMEOUT);

  it('should render the editor', async () => {
    await page.waitForSelector('.liveblog-editor-container');
    const editor = await page.$('.liveblog-editor-container');
    expect(editor).toBeDefined();
  }, TIMEOUT);

  it('should render an entry', async () => {
    await page.waitForSelector('.public-DraftEditor-content');
    await renderEntry();
    await page.waitForSelector('.liveblog-entry');
    const entry = await page.$('.liveblog-entry');
    expect(entry).toBeDefined();
  }, TIMEOUT);

  it('should delete an entry', async () => {
    await page.click('.liveblog-btn-delete');
    const entryExists = await page.$$eval('.liveblog-entry', deleted => deleted);
    expect(entryExists).toBeFalsy();
  }, TIMEOUT);

  it('should render multiple entries', async () => {
    await renderEntries(15);
    await page.reload({ waitUntil: ['load', 'networkidle0'] });
    const pagination = await page.evaluate(() =>
      document.querySelector('.liveblog-pagination-pages').innerHTML,
    );
    expect(pagination).toEqual('1 of 3');
  }, TIMEOUT);

  it('should render different pages', async () => {
    await page.click('.liveblog-pagination-next');
    const feedChildrenCount = await page.evaluate(() =>
      document.querySelector('.liveblog-feed').children.length,
    );
    const pagination = await page.evaluate(() =>
      document.querySelector('.liveblog-pagination-pages').innerHTML,
    );
    expect(pagination).toEqual('2 of 3');
    expect(feedChildrenCount).toEqual(5);
  }, TIMEOUT);

  it('should add a key event', async () => {
    await page.click('.liveblog-pagination-prev');
    await renderEntry('/key This is a test key event');
    await page.waitForSelector('.liveblog-event');
    const event = await page.$('.liveblog-event');
    expect(event).toBeDefined();
  });

  it('should take you to a key event on click', async () => {
    await page.click('.liveblog-pagination-last');
    await page.click('.liveblog-event');
    await page.waitForSelector('.is-key-event');
    const event = await page.$('.is-key-event');
    expect(event).toBeDefined();
  });

  afterAll(async () => {
    await page.close();
    await browser.disconnect();
    await browser.close();
  }, TIMEOUT);
}, TIMEOUT);
