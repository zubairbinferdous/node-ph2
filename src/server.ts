import { createServer, IncomingMessage, Server, ServerResponse } from "node:http";
import routeHandler from "./routes/route";
import { config } from "./config";

const server: Server = createServer((req: IncomingMessage, res: ServerResponse) => {

    // console.log( "request", req);
    // console.log( "response", res);
    // console.log( "url", req.url);
    // console.log( "method", req.method);
    // const url = req.url;
    // const method = req.method;

    // if (url === '/' && method === "GET") {
    //     console.log('hello world this is me to make a request');
    // } else {
    //     console.log("this route is not working ")
    // }

    // if (url === '/' && method === "GET") {
    //     res.writeHead(200, { "Content-Type": "application/json" });
    //     res.end(JSON.stringify({ message: "this is a get server " }));

    // } else if (url?.startsWith("/products")) {
    //     res.writeHead(200, { "Content-Type": "application/json" });
    //     res.end(JSON.stringify({ message: "this is product page " }));
    // }
    // else {
    //     res.writeHead(400, { "Content-Type": "application/json" });
    //     res.end(JSON.stringify({ message: " you have no access " }));
    // }

    routeHandler(req, res);
});

server.listen(config.prot, () => {
    console.log(`server running on ${config.prot}`);
});








