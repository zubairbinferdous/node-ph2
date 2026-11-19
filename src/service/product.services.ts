import fs from "node:fs";
import path from "node:path";
import type { Iproduct } from "../types/productType";

const filePath = path.join(process.cwd(), "./src/database/db.json");

export const productService = () => {
    // console.log(filePath);
    const result = fs.readFileSync(filePath, "utf-8");
    // console.log(result);
    return JSON.parse(result);
}

export const createProduct = (payload: Iproduct) => {
    fs.writeFileSync(filePath, JSON.stringify(payload));
}
