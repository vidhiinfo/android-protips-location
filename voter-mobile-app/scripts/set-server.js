#!/usr/bin/env node
/*
 * Point the app at your own installation of the Voter Information System.
 *   npm run server -- https://electionsoft.in/vis
 * Updates capacitor.config.json (start page + allowed host). Run "npx cap sync" afterwards and rebuild.
 */
const fs = require('fs');
const path = require('path');
const arg = process.argv[2];
if (!arg || !/^https:\/\/[^/]+/.test(arg)) {
  console.error('Usage: npm run server -- https://your-domain/path   (https only)');
  process.exit(1);
}
const base = arg.replace(/\/+$/, '');
const file = path.join(__dirname, '..', 'capacitor.config.json');
const cfg = JSON.parse(fs.readFileSync(file, 'utf8'));
const host = new URL(base).hostname;
cfg.server.url = base + '/login';
cfg.server.allowNavigation = Array.from(new Set([host, host.startsWith('www.') ? host.slice(4) : 'www.' + host]));
fs.writeFileSync(file, JSON.stringify(cfg, null, 2) + '\n');
console.log('App now opens ' + cfg.server.url + ' (allowed hosts: ' + cfg.server.allowNavigation.join(', ') + ')');
