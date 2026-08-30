const http=require('http');
const WebSocket=require('ws');
const DeveloperRealtimeHub=require('../src/services/developerRealtimeHub');

const target=Number(process.env.DEVELOPER_WS_LOAD_CONNECTIONS||1000);
const batchSize=Math.max(1,Number(process.env.DEVELOPER_WS_LOAD_BATCH_SIZE||50));
const batchPauseMs=Math.max(0,Number(process.env.DEVELOPER_WS_LOAD_BATCH_PAUSE_MS||25));
const authority={authorize:async({session_id,environment})=>({session_uuid:session_id,project_uuid:`project-${session_id}`,project_id:Number(session_id.split('_').pop()),api_key_uuid:`key-${session_id}`,environment,topics:['account.balance']}),replay:async()=>({events:[],latest_sequence:0,reconcile_required:false})};
const server=http.createServer();const hub=new DeveloperRealtimeHub(authority);hub.attach(server);
server.listen({port:0,host:'127.0.0.1',backlog:2048},async()=>{
  const port=server.address().port;const started=Date.now();let authenticated=0;let failed=0;const clients=[];
  const connect=(i)=>new Promise((resolve)=>{
    const ws=new WebSocket(`ws://127.0.0.1:${port}/ws/developer/sandbox`);clients.push(ws);
    const timer=setTimeout(()=>{failed++;resolve();},15000);
    ws.on('message',(raw)=>{const message=JSON.parse(String(raw));if(message.op==='connected')ws.send(JSON.stringify({op:'authenticate',session_id:`devws_${i+1}`}));if(message.op==='authenticated'){clearTimeout(timer);authenticated++;resolve();}});
    ws.on('error',()=>{clearTimeout(timer);failed++;resolve();});
  });
  for(let offset=0;offset<target;offset+=batchSize){const pending=[];for(let i=offset;i<Math.min(offset+batchSize,target);i++)pending.push(connect(i));await Promise.all(pending);if(batchPauseMs)await new Promise((resolve)=>setTimeout(resolve,batchPauseMs));}
  const result={target,authenticated,failed,batch_size:batchSize,batch_pause_ms:batchPauseMs,duration_ms:Date.now()-started,memory_rss_mb:Math.round(process.memoryUsage().rss/1024/1024),gateway:hub.stats()};
  process.stdout.write(`${JSON.stringify(result)}\n`);clients.forEach((ws)=>ws.terminate());clearInterval(hub.heartbeat);server.close(()=>process.exit(failed?1:0));
});
