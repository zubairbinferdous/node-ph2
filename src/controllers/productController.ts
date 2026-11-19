import type { IncomingMessage, ServerResponse } from "node:http";
import { productService, createProduct } from "../service/product.services";
import type { Iproduct } from "../types/productType";
import { bodyParser } from "../utils/parseBody";
import { create } from "node:domain";

const productController = async (req: IncomingMessage, res: ServerResponse) => {

    const url = req.url;
    const method = req.method;
    const urlParam = url?.split("/");
    const products = productService();
    const id = urlParam && urlParam[1] === "products" ? Number(urlParam[2]) : null;

    if (url === "/products" && method === "GET") {

        res.writeHead(200, { "Content-Type": "application/json" });
        res.end(JSON.stringify({
            message: " we show all product ",
            data: products
        }));

    } else if (method === "GET" && id !== null) {
        const product = products.find((p: Iproduct) => p.id === id);
        res.writeHead(200, { "Content-Type": "application/json" });
        res.end(JSON.stringify({
            message: " we show all product ",
            data: product
        }));
    } else if (method === "POST" && url === "/products") {

        const body = await bodyParser(req);
        const newProduct = {
            id: Date.now(),
            ...body
        }
        products.push(newProduct);
        createProduct(products);

        res.writeHead(201, { "Content-Type": "application/json" });
        res.end(JSON.stringify({
            message: "create product successfully",
            data: newProduct
        }));
    }


};

export default productController;