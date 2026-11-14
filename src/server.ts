import { createServer } from "node:http";

const server = createServer((req, res) => {
    console.log(res);
});

server.listen(5000, () => {
    console.log('server is running now');
})
