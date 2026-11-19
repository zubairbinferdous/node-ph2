import type { IncomingMessage, ServerResponse } from "node:http";
import { productService, createProduct } from "../service/product.services";
import type { Iproduct } from "../types/productType";
import { bodyParser } from "../utils/parseBody";
import { create } from "node:domain";
import { sendResponse } from "../utils/sendResponse";

const productController = async (req: IncomingMessage, res: ServerResponse) => {

    const url = req.url;
    const method = req.method;
    const urlParam = url?.split("/");
    const products = productService();
    const id = urlParam && urlParam[1] === "products" ? Number(urlParam[2]) : null;
    // get all product 
    if (url === "/products" && method === "GET") {
        try {
            sendResponse(res, true, 200, "Get all products", products);
        } catch (error) {
            sendResponse(res, false, 500, "Internal server error");
        }
    }
    // get a single product 
    else if (method === "GET" && id !== null) {
        const product = products.find((p: Iproduct) => p.id === id);
        try {

            if (!product) {
                return sendResponse(res, false, 404, "Product not found");
            }

        } catch (error) {
            sendResponse(res, true, 200, "get update product", product);
        }

    }
    // post a new product 
    else if (method === "POST" && url === "/products") {

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
    // update a new product 
    else if (method === "PUT" && id !== null) {

        const body = await bodyParser(req);
        const index = products.findIndex((p: Iproduct) => p.id === id);

        if (index < 0) {
            res.writeHead(404, { "Content-Type": "application/json" });
            res.end(JSON.stringify({
                message: "product not found ",
            }));
        }

        products[index] = { id: products[index].id, ...body };
        createProduct(products);

        res.writeHead(200, { "Content-Type": "application/json" });
        res.end(JSON.stringify({
            message: "product update successfully",
            data: index
        }));
    }
    // delete a product 
    else if (method === "DELETE" && id !== null) {
        const index = products.findIndex((p: Iproduct) => p.id === id);

        if (index < 0) {
            res.writeHead(404, { "Content-Type": "application/json" });
            res.end(JSON.stringify({
                message: "product not found ",
            }));
        }

        products.splice(index, 1);
        createProduct(products);

        res.writeHead(200, { "Content-Type": "application/json" });
        res.end(JSON.stringify({
            message: "product delete successfully",
            data: index
        }));
    }


};

export default productController;