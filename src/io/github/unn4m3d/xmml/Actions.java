package io.github.unn4m3d.xmml;

import java.io.File;
import java.io.IOException;
import java.net.MalformedURLException;
import java.net.URL;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.LinkedHashSet;
import java.util.Map.Entry;
import java.util.Set;

import io.github.unn4m3d.xmml.files.Guard;
import io.github.unn4m3d.xmml.web.HTTPUtils;
import net.launcher.Settings;
import net.launcher.utils.ClientUtils;

import org.json.simple.*;
import org.json.simple.parser.ParseException;

public class Actions {
	
	@SuppressWarnings("unchecked")
	public static JSONObject auth(String login, String password) throws MalformedURLException, ParseException, IOException{
		JSONObject j = new JSONObject();
		j.put("action", "auth");
		j.put("login",login);
		j.put("pass",password);
		j.put("md5", Guard.getMD5(Actions.class.getProtectionDomain().getCodeSource().toString()));
		j.put("version", Settings.version);
		return HTTPUtils.query(new URL(Settings.webpath + "/launcher.php"), j, new HashMap<String,String>());
	}
	
	public static boolean checkMods(JSONArray in){
		File[] f = new File(ClientUtils.getMcDir(),"mods").listFiles();
		HashMap<String,File> local = new HashMap<String,File>();
		for(File file : f){
			//local.add(file);
			local.put(file.getName(), file);
		}
			
		for(Object o : in){
			JSONObject obj = (JSONObject) o;
			Log.send("Checking file " + (String)obj.get("path"));
			String path = ((String)obj.get("path"));
			if(!path.matches("/mods/")) continue;
			path = path.replaceFirst(".*/mods/","");
			if((boolean)obj.get("check") == false){
				try{
					local.remove(path);
				}catch(Exception e){ e.printStackTrace();}
				continue;
			}
			
			if(!new File(ClientUtils.getMcDir(),path).exists()) return false;
			if(!local.containsKey(path) || Guard.getMD5(local.get(path).getAbsolutePath()) != (String)obj.get("md5")) return false;
			
				try{
					local.remove(path);
				}catch(Exception e){e.printStackTrace();}
			}
		for(Entry<String,File> e : local.entrySet()){
			try{
				if(e.getValue().isDirectory()) local.remove(e.getKey());
			}catch(Exception ex){ex.printStackTrace();}
		}
		return (local.size() <= 0);
	}

}
