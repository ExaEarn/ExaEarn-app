const http = require('http');
const WebSocket = require('ws');
const DeveloperRealtimeHub = require('../developerRealtimeHub');

function opened(socket) { return new Promise((resolve, reject) => { socket.once('open',resolve);socket.once('error',reject); }); }
function inbox(socket) { const queued=[];const waiters=[];socket.on('message',(data)=>{const message=JSON.parse(String(data));const waiter=waiters.shift();if(waiter)waiter(message);else queued.push(message);});return()=>queued.length?Promise.resolve(queued.shift()):new Promise((resolve)=>waiters.push(resolve)); }

describe('DeveloperRealtimeHub network gateway', () => {
  let server; let hub; let port; let revoked;
  beforeEach(async () => {
    revoked=false;
    const authority={
      authorize: jest.fn(async ({session_id,environment}) => { if(revoked||session_id!=='devws_valid'||environment!=='sandbox')throw new Error('denied'); return {session_uuid:'session-1',project_uuid:'project-1',project_id:7,api_key_uuid:'key-1',environment:'sandbox',topics:['account.balance','order']}; }),
      replay: jest.fn(async ({stream,after_sequence}) => ({events:[{event_id:'evt-2',stream,sequence:after_sequence+1,event_type:'account.balance.updated',payload:{asset:'USDT'},timestamp:'2026-08-30T00:00:00Z'}],latest_sequence:after_sequence+1,reconcile_required:false})),
    };
    server=http.createServer();hub=new DeveloperRealtimeHub(authority);hub.attach(server);
    await new Promise((resolve)=>server.listen(0,'127.0.0.1',resolve));port=server.address().port;
  });
  afterEach(async()=>{ for(const client of hub.clients)client.terminate(); clearInterval(hub.heartbeat); await new Promise((resolve)=>server.close(resolve)); });

  test('authenticates, replays, publishes live event and rejects cross-environment session', async () => {
    const ws=new WebSocket(`ws://127.0.0.1:${port}/ws/developer/sandbox`);const next=inbox(ws);await opened(ws);expect((await next()).op).toBe('connected');
    ws.send(JSON.stringify({op:'authenticate',session_id:'devws_valid'}));expect((await next()).op).toBe('authenticated');
    ws.send(JSON.stringify({op:'replay',stream:'account.balance',after_sequence:4}));const replay=await next();expect(replay.events[0].sequence).toBe(5);
    const live=next();hub.publish({project_id:7,environment:'sandbox',event_id:'evt-live',stream:'order',sequence:1,event_type:'order.updated',payload:{}});expect((await live).event_id).toBe('evt-live');
    ws.terminate();
    const wrong=new WebSocket(`ws://127.0.0.1:${port}/ws/developer/production`);const wrongNext=inbox(wrong);await opened(wrong);await wrongNext();wrong.send(JSON.stringify({op:'authenticate',session_id:'devws_valid'}));const close=await new Promise((resolve)=>wrong.once('close',(code)=>resolve(code)));expect(close).toBe(4403);
  });

  test('revocation closes an existing real network connection', async () => {
    const ws=new WebSocket(`ws://127.0.0.1:${port}/ws/developer/sandbox`);const next=inbox(ws);await opened(ws);await next();ws.send(JSON.stringify({op:'authenticate',session_id:'devws_valid'}));await next();
    revoked=true;const closed=new Promise((resolve)=>ws.once('close',(code)=>resolve(code)));await hub._heartbeat();expect(await closed).toBe(4403);
  });

  test('malformed and unauthorized messages fail safely', async () => {
    const ws=new WebSocket(`ws://127.0.0.1:${port}/ws/developer/sandbox`);const next=inbox(ws);await opened(ws);await next();ws.send('{');expect((await next()).code).toBe('INVALID_JSON');
    ws.send(JSON.stringify({op:'authenticate',session_id:'devws_valid'}));await next();ws.send(JSON.stringify({op:'subscribe',topics:['withdrawal']}));expect((await next()).code).toBe('TOPIC_NOT_AUTHORIZED');ws.terminate();
  });
});
