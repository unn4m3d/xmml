package io.github.unn4m3d.xmml.web;

import java.io.BufferedReader;
import java.io.DataOutputStream;
import java.io.IOException;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.io.UnsupportedEncodingException;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLEncoder;
import java.util.ArrayList;
import java.util.Formatter;
import java.util.HashMap;
import java.util.List;
import java.util.Map.Entry;

import net.launcher.Settings;
import net.launcher.utils.Crypt;

import org.json.simple.JSONObject;
import org.json.simple.parser.JSONParser;
import org.json.simple.parser.ParseException;

public class HTTPUtils {
	
	public final static String USER_AGENT = "Mozilla/5.0";
	
	public static String POST(URL addr, String data) throws IOException{
		HttpURLConnection conn = (HttpURLConnection)addr.openConnection();
		
		conn.setRequestMethod("POST");
		conn.setRequestProperty("User-Agent", USER_AGENT);
		conn.setRequestProperty("Accept-Language", "en-US,en;q=0.5");
		conn.setReadTimeout(100000);
		
		conn.setDoOutput(true);
		
		OutputStream s = conn.getOutputStream();
		s.write(data.getBytes("UTF-8"));
		s.flush();
		s.close();
		
		System.out.println(conn.getResponseCode());
		System.out.println(conn.getResponseMessage());
		BufferedReader r = new BufferedReader(new InputStreamReader(conn.getInputStream()));
		
		StringBuilder builder = new StringBuilder();
		
		String l;
		while((l = r.readLine()) != null){
			builder.append(l);
		}
		
		r.close();
		
		return builder.toString();
		
		//return ""; //Чтоб жава не ругалась
	}
	
	public static String POST(URL addr, HashMap<String,String> data) throws IOException{
		ArrayList<String> tok = new ArrayList<String>();
		for(Entry<String, String> e : data.entrySet()){
			tok.add(new Formatter().format(
					"%s=%s",
					e.getKey(),
					URLEncoder.encode(e.getValue(),"UTF-8")
				).toString());
		}
		return POST(addr,join(tok,"&"));
	}
	
	public static String join(List<String> s, String sep){
		String r = s.get(0);
		for(int i = 1; i < s.size(); i++){
			r += sep + s.get(i);
		}
		return r;
	}
	
	public static JSONObject query(URL addr, JSONObject action, HashMap<String,String> af) throws ParseException, IOException{
		af.put("action", Crypt.encrypt(action.toJSONString(),Settings.key2));
		JSONParser j = new JSONParser();
		return (JSONObject) j.parse(Crypt.decrypt(POST(addr,af),Settings.key1));
	}
}
