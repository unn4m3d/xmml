package io.github.unn4m3d.xmml;

import com.googlecode.lanterna.TerminalFacade;
import com.googlecode.lanterna.gui.*;
import com.googlecode.lanterna.input.Key;
import com.googlecode.lanterna.screen.Screen;

import net.launcher.*;

public class Launcher extends Window {

	protected Thread keyListenerThread;
	/**
	 * @param args
	 */
	public static void main(String[] args) {
		final GUIScreen s = TerminalFacade.createGUIScreen();
		Launcher l = new Launcher(s);
		s.getScreen().startScreen();
		s.showWindow(l, GUIScreen.Position.FULL_SCREEN);
		try {
			l.keyListenerThread.join();
		} catch (InterruptedException e) {
			// TODO Auto-generated catch block
			e.printStackTrace();
		}
	}
	
	public Launcher(final GUIScreen s){
		super(Settings.title);
		keyListenerThread = new Thread(){
			public void run(){
				System.out.println("KL Started!");
				while(true){
					Key key = s.getScreen().readInput();
					if(key == null) continue;
					//System.out.printf("Key pressed : %s\n", (key.getKind() == Key.Kind.NormalKey ? key.getCharacter() : key.getKind().toString()) );
					if(key.getKind() == Key.Kind.Escape) {
						s.getScreen().stopScreen();
						System.exit(0);
					}
				}
			}
		};
		keyListenerThread.setName("Key Listener");
		keyListenerThread.start();
	}

}
