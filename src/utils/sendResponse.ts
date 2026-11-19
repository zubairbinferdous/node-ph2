import { ServerResponse } from "node:http";

export const sendResponse = (
    res: ServerResponse,
    success: boolean,
    statusCode: number,
    massage: string,
    data?: any
) => {
    const response = {
        success,
        massage,
        data
    }
    res.writeHead(statusCode, { "Content-Type": "application/json" });
    res.end(JSON.stringify(response));

}