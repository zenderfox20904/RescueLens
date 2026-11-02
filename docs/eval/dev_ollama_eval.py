# -*- coding: utf-8 -*-
import json, urllib.request, uuid, time, sys
B='http://localhost:8010/api/v1'
def req(m,p,data=None,tok=None,ct='application/json',hdr=None):
    h={'Accept':'application/json'}
    if tok: h['Authorization']='Bearer '+tok
    if hdr: h.update(hdr)
    body=None
    if data is not None:
        if ct=='application/json': body=json.dumps(data).encode(); h['Content-Type']=ct
        else: body,h['Content-Type']=data,ct
    r=urllib.request.Request(B+p,body,h,method=m)
    try:
        with urllib.request.urlopen(r,timeout=400) as x: return x.status,json.loads(x.read() or b'{}')
    except urllib.error.HTTPError as e: return e.code,json.loads(e.read() or b'{}')
def upload(tok,path):
    bd='----rl'+uuid.uuid4().hex; img=open(path,'rb').read()
    body=(f'--{bd}\r\nContent-Disposition: form-data; name="image"; filename="x.jpg"\r\nContent-Type: image/jpeg\r\n\r\n').encode()+img+f'\r\n--{bd}--\r\n'.encode()
    return req('POST','/media',body,tok,'multipart/form-data; boundary='+bd)
s,r=req('POST','/auth/register',{'name':'Ollama Eval','email':f'eval{uuid.uuid4().hex[:6]}@example.com','password':'evalpass123'}); tok=r['token']
out={}
for img in ['accident1','accident2','accident3','control']:
    s,m=upload(tok,img+'.jpg'); mid=m['id']
    for lang in (['en','bn'] if img in('accident1','control') else ['en']):
        t=time.time(); s,a=req('POST','/analyses',{'media_id':mid,'ai_consent':True,'language':lang},tok); dt=round(time.time()-t,1)
        an=a.get('analysis',a); res=an.get('result') or {}
        out[f'{img}-{lang}']={'http':s,'secs':dt,'status':an.get('status'),'mode':an.get('mode'),'model':an.get('vision_model'),'error':an.get('error_code'),
          'primary_hazard':an.get('primary_hazard'),'severity':an.get('severity'),'is_scene':res.get('is_incident_scene'),
          'title':res.get('suggested_title'),'summary':res.get('summary'),'hazards':res.get('possible_hazards'),'people_at_risk':res.get('people_at_risk'),'requires_review':an.get('requires_human_review')}
        print(img,lang,s,dt,an.get('status'),an.get('primary_hazard'),an.get('severity'),'|',res.get('suggested_title'),flush=True)
        if img=='accident1' and lang=='en' and an.get('status')=='completed':
            s2,p=req('POST','/incidents',{'title':res['suggested_title'],'description':res.get('summary'),'category':an['primary_hazard'] if an['primary_hazard'] else 'other','severity':an['severity'],
              'lat':23.75,'lng':90.39,'media_ids':[mid],'analysis_id':an['id'],'consent_public':True},tok)
            out['post']={'http':s2,'id':p.get('data',{}).get('id'),'title':p.get('data',{}).get('title'),'ai_assisted':p.get('data',{}).get('ai_assisted'),'source':p.get('data',{}).get('source')}
            print('POST',out['post'],flush=True)
json.dump(out,open('results.json','w'),ensure_ascii=False,indent=1)
