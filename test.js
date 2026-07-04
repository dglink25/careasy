import http from 'k6/http';
import { check, sleep } from 'k6';

const vus = Number(__ENV.VUS) || 20;
const duration = __ENV.DURATION || '2m';

export const options = {
  vus,
  duration,
};

export default function () {
  const res = http.get('https://careasy.cap-epac.bj/api/services', {
    timeout: '60s',
  });

  check(res, {
    'status 200': (r) => r.status === 200,
  });

  sleep(1);
}