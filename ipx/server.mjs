/*
 * IPX for ux.symfony.com, run by Upsun (Platform.sh) next to the Symfony application.
 *
 * The router sends /_ipx/<modifiers>/<path> here (.platform/routes.yaml). IPX
 * fetches <path> from the Symfony application through the "app" relationship:
 * the internal network of the environment, where every environment serves its
 * own files.
 */
import { createIPX, ipxHttpStorage, parseIPXURL, serveIPX } from 'ipx';

const [app] = JSON.parse(Buffer.from(process.env.PLATFORM_RELATIONSHIPS, 'base64').toString()).app;
const origin = `http://${app.host}:${app.port}`;

const ipx = createIPX({
    // /uploads/hero.jpg is the file the application serves at <origin>/uploads/hero.jpg
    alias: { '/': origin },
    httpStorage: ipxHttpStorage({ domains: [app.host] }),
});

serveIPX(ipx, {
    port: Number(process.env.PORT),
    // IPX reads /<modifiers>/<path>: the prefix of the route goes first
    parseURL(url) {
        const target = new URL(url);
        target.pathname = target.pathname.replace(/^\/_ipx(?=\/)/, '');

        return parseIPXURL(target.href);
    },
});
