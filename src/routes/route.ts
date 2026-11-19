import type { IncomingMessage, ServerResponse } from "node:http";
import productController from "../controllers/productController";

const routeHandler = (req: IncomingMessage, res: ServerResponse) => {

    const url = req.url;
    const method = req.method;

    if (url === '/' && method === "GET") {
        res.writeHead(200, { "Content-Type": "application/json" });
        res.end(JSON.stringify({ message: " this is get sms for / find " }));

    } else if (url?.startsWith("/products")) {
        productController(req, res);
    }
    else {
        res.writeHead(400, { "Content-Type": "application/json" });
        res.end(JSON.stringify({ message: " there is no route is find no access. " }));
    }

};

export default routeHandler;